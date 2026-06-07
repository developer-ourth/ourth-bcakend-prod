<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Events\OrderLocationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Consumer\CheckoutRequest;
use App\Models\Cart;
use App\Models\DeliveryTracking;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MobileOrderController
 *
 * Handles order placement (checkout from cart) and order history
 * for the authenticated consumer.
 */
class MobileOrderController extends Controller
{
    /**
     * List the authenticated consumer's orders.
     *
     * GET /api/v1/me/orders
     * Query: status, page, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Order::where('user_id', $request->user()->id)
            ->select(['id', 'order_number', 'vendor_id', 'order_status', 'payment_status', 'total_amount', 'created_at', 'delivered_at', 'cancelled_at'])
            ->with('vendor:id,business_name,logo_url')
            ->withCount('items');

        if ($request->filled('status')) {
            $query->where('order_status', $request->status);
        }

        $orders = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
            ],
        ]);
    }

    /**
     * Get a single order's full detail.
     *
     * GET /api/v1/me/orders/{order}
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        abort_if($order->user_id !== $request->user()->id, 403, 'Forbidden.');

        $order->load(['vendor:id,business_name,logo_url,city', 'items.product:id,name,primary_image_url']);

        return response()->json(['success' => true, 'data' => $order]);
    }

    /**
     * Cancel an order (consumer self-service).
     * Only allowed while the order is still pending.
     *
     * POST /api/v1/me/orders/{order}/cancel
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        abort_if($order->user_id !== $request->user()->id, 403, 'Forbidden.');

        if ($order->order_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending orders can be cancelled.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($order, $validated) {
            $order->update([
                'order_status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $validated['reason'] ?? 'Cancelled by customer.',
            ]);

            // Release reserved stock for tracked products
            foreach ($order->load('items.product.inventory')->items as $item) {
                if ($item->product?->inventory && $item->product->inventory->reserved_stock >= $item->quantity) {
                    $item->product->inventory->decrement('reserved_stock', $item->quantity);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled.',
            'data' => $order->refresh(),
        ]);
    }

    /**
     * Create a Razorpay order for an existing pending order.
     *
     * POST /api/v1/me/orders/{order}/payments/razorpay/initiate
     */
    public function initiateRazorpayPayment(Request $request, Order $order): JsonResponse
    {
        abort_if($order->user_id !== $request->user()->id, 403, 'Forbidden.');

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'This order is already paid.',
            ], 422);
        }

        if ($order->order_status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot initiate payment for a cancelled order.',
            ], 422);
        }

        $key = (string) config('services.razorpay.key');
        $secret = (string) config('services.razorpay.secret');
        $baseUrl = rtrim((string) config('services.razorpay.base_url', 'https://api.razorpay.com'), '/');

        if ($key === '' || $secret === '') {
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway is not configured. Please contact support.',
            ], 500);
        }

        $amountPaise = (int) round(((float) $order->total_amount) * 100);

        $response = Http::withBasicAuth($key, $secret)
            ->acceptJson()
            ->post("{$baseUrl}/v1/orders", [
                'amount' => $amountPaise,
                'currency' => 'INR',
                'receipt' => $order->order_number,
                'notes' => [
                    'app_order_id' => (string) $order->id,
                    'order_number' => $order->order_number,
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Razorpay order initiation failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to initiate online payment at the moment.',
            ], 502);
        }

        $gatewayOrder = $response->json();

        $payment = $order->payment;
        if (! $payment) {
            $payment = new Payment(['order_id' => $order->id]);
        }

        $payment->fill([
            'payment_gateway' => 'razorpay',
            'payment_method' => 'online',
            'amount' => $order->total_amount,
            'status' => 'pending',
            'transaction_id' => $gatewayOrder['id'] ?? null,
            'gateway_response' => $gatewayOrder,
            'error_code' => null,
            'error_message' => null,
            'paid_at' => null,
        ]);
        $payment->save();

        return response()->json([
            'success' => true,
            'data' => [
                'key' => $key,
                'razorpay_order_id' => $gatewayOrder['id'] ?? null,
                'amount' => $gatewayOrder['amount'] ?? $amountPaise,
                'currency' => $gatewayOrder['currency'] ?? 'INR',
                'order_id' => $order->id,
            ],
        ]);
    }

    /**
     * Verify Razorpay payment signature and mark order as paid.
     *
     * POST /api/v1/me/orders/{order}/payments/razorpay/verify
     */
    public function verifyRazorpayPayment(Request $request, Order $order): JsonResponse
    {
        abort_if($order->user_id !== $request->user()->id, 403, 'Forbidden.');

        $validated = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $secret = (string) config('services.razorpay.secret');
        if ($secret === '') {
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway is not configured. Please contact support.',
            ], 500);
        }

        $payload = $validated['razorpay_order_id'].'|'.$validated['razorpay_payment_id'];
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expectedSignature, $validated['razorpay_signature'])) {
            $order->payment()?->update([
                'status' => 'failed',
                'error_code' => 'signature_mismatch',
                'error_message' => 'Payment signature verification failed.',
                'gateway_response' => array_merge($order->payment?->gateway_response ?? [], [
                    'verify_payload' => $validated,
                ]),
            ]);

            $order->update(['payment_status' => 'failed']);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed.',
            ], 422);
        }

        DB::transaction(function () use ($order, $validated) {
            $payment = $order->payment;
            if (! $payment) {
                $payment = new Payment([
                    'order_id' => $order->id,
                    'payment_gateway' => 'razorpay',
                    'payment_method' => 'online',
                    'amount' => $order->total_amount,
                ]);
            }

            $payment->fill([
                'transaction_id' => $validated['razorpay_payment_id'],
                'status' => 'completed',
                'paid_at' => now(),
                'error_code' => null,
                'error_message' => null,
                'gateway_response' => array_merge($payment->gateway_response ?? [], [
                    'verification' => [
                        'razorpay_order_id' => $validated['razorpay_order_id'],
                        'razorpay_payment_id' => $validated['razorpay_payment_id'],
                        'razorpay_signature' => $validated['razorpay_signature'],
                    ],
                ]),
            ]);
            $payment->save();

            $order->update(['payment_status' => 'paid']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Payment verified successfully.',
            'data' => $order->refresh()->load('payment'),
        ]);
    }

    /**
     * Checkout: convert the active cart into an order.
     *
     * POST /api/v1/me/orders
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $cart = Cart::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('items.product')
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Your cart is empty.'], 422);
        }

        $invalidItem = $cart->items->first(function ($item) {
            return ! $item->product || ! $item->product->vendor_id;
        });

        if ($invalidItem) {
            return response()->json([
                'success' => false,
                'message' => 'One or more items in your cart are unavailable. Please remove them and try again.',
            ], 422);
        }

        $vendorIds = $cart->items
            ->map(fn ($item) => $item->product?->vendor_id)
            ->filter()
            ->unique()
            ->values();

        if ($vendorIds->count() > 1) {
            return response()->json([
                'success' => false,
                'message' => 'Your cart contains items from multiple vendors. Please keep items from one vendor only.',
            ], 422);
        }

        // Resolve vendor_id: prefer cart vendor, then validated cart item vendor.
        $vendorId = $cart->vendor_id ?? $vendorIds->first();

        if (! $vendorId) {
            return response()->json(['success' => false, 'message' => 'Unable to determine vendor for this order. Please contact support.'], 422);
        }

        if (! $cart->vendor_id) {
            $cart->update(['vendor_id' => $vendorId]);
        }

        $isB2B = ($validated['order_type'] ?? 'b2c') === 'b2b';
        $orderType = $isB2B ? 'b2b' : 'b2c';

        // Validate stock availability and B2B MOQ before creating the order
        foreach ($cart->items as $item) {
            $inventory = $item->product->inventory;

            // If no inventory record exists, stock is not tracked — allow the order
            if ($inventory === null) {
                continue;
            }

            $available = $inventory->available_stock;
            if ($available < $item->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient stock for \"{$item->product->name}\". Available: {$available}.",
                ], 422);
            }

            // Enforce minimum order quantity for B2B orders
            $moq = (int) ($item->product->min_order_quantity ?? 1);
            if ($isB2B && $item->quantity < $moq) {
                return response()->json([
                    'success' => false,
                    'message' => "Minimum order quantity for \"{$item->product->name}\" is {$moq} units.",
                ], 422);
            }
        }

        $order = DB::transaction(function () use ($user, $cart, $validated, $vendorId, $isB2B, $orderType) {
            // Resolve per-item unit price: use wholesale_price for B2B if set
            $lineItems = $cart->items->map(function ($item) use ($isB2B) {
                $product = $item->product;
                $unitPrice = $isB2B && $product->wholesale_price !== null
                    ? (float) $product->wholesale_price
                    : (float) $item->unit_price;

                return [
                    'item' => $item,
                    'unit_price' => $unitPrice,
                    'total' => $unitPrice * $item->quantity,
                ];
            });

            $subtotal = $lineItems->sum('total');

            $order = Order::create([
                'user_id' => $user->id,
                'vendor_id' => $vendorId,
                'buyer_vendor_id' => $user->vendor?->id,
                'order_type' => $orderType,
                'buyer_gstin' => $isB2B ? ($validated['buyer_gstin'] ?? null) : null,
                'order_status' => 'pending',
                'payment_status' => 'pending',
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'delivery_charge' => 0,
                'tax_amount' => 0,
                'total_amount' => $subtotal,
                'delivery_address_line1' => $validated['delivery_address_line1'],
                'delivery_address_line2' => $validated['delivery_address_line2'] ?? null,
                'delivery_city' => $validated['delivery_city'],
                'delivery_state' => $validated['delivery_state'],
                'delivery_postal_code' => $validated['delivery_postal_code'],
                'delivery_country' => $validated['delivery_country'] ?? 'India',
                'delivery_phone' => $validated['delivery_phone'],
                'customer_notes' => $validated['customer_notes'] ?? null,
            ]);

            foreach ($lineItems as $line) {
                $item = $line['item'];
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'product_sku' => $item->product->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $line['unit_price'],
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total_price' => $line['total'],
                ]);

                // Decrement reserved stock
                if ($item->product->inventory) {
                    $item->product->inventory->increment('reserved_stock', $item->quantity);
                }
            }

            Payment::create([
                'order_id' => $order->id,
                'payment_gateway' => $validated['payment_method'] === 'cod' ? 'cod' : 'razorpay',
                'payment_method' => $validated['payment_method'],
                'amount' => $subtotal,
                'status' => 'pending',
            ]);

            // Mark cart as converted
            $cart->update([
                'status' => 'converted_to_order',
                'converted_at' => now(),
                'converted_to_order_id' => $order->id,
            ]);

            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully.',
            'data' => $order->load(['items', 'vendor:id,business_name']),
        ], 201);
    }

    /**
     * Get live tracking state for an order.
     *
     * GET /api/v1/me/orders/{order}/tracking
     */
    public function tracking(Request $request, Order $order): JsonResponse
    {
        abort_if($order->user_id !== $request->user()->id, 403, 'Forbidden.');

        $order->load('vendor:id,business_name,logo_url,latitude,longitude,city');
        $tracking = DeliveryTracking::where('order_id', $order->id)->first();

        $pickup = null;
        if ($order->vendor && $order->vendor->latitude && $order->vendor->longitude) {
            $pickup = [
                'lat' => (float) $order->vendor->latitude,
                'lng' => (float) $order->vendor->longitude,
                'name' => $order->vendor->business_name,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_status' => $order->order_status,
                'pickup' => $pickup,
                'rider' => $tracking ? [
                    'lat' => $tracking->rider_lat,
                    'lng' => $tracking->rider_lng,
                    'bearing' => $tracking->bearing,
                    'status_message' => $tracking->status_message,
                    'updated_at' => $tracking->updated_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    /**
     * Update rider GPS location (vendor / admin only).
     *
     * POST /api/v1/orders/{order}/tracking/location
     */
    public function updateLocation(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'rider_lat' => ['required', 'numeric', 'between:-90,90'],
            'rider_lng' => ['required', 'numeric', 'between:-180,180'],
            'bearing' => ['nullable', 'numeric', 'between:0,360'],
            'status_message' => ['nullable', 'string', 'max:120'],
        ]);

        $tracking = DeliveryTracking::updateOrCreate(
            ['order_id' => $order->id],
            [
                'rider_lat' => $validated['rider_lat'],
                'rider_lng' => $validated['rider_lng'],
                'bearing' => $validated['bearing'] ?? 0,
                'status_message' => $validated['status_message'] ?? null,
            ]
        );

        OrderLocationUpdated::dispatch(
            $order,
            (float) $tracking->rider_lat,
            (float) $tracking->rider_lng,
            (float) $tracking->bearing,
            $tracking->status_message,
        );

        return response()->json(['success' => true, 'data' => $tracking]);
    }

    /**
     * Download a PDF invoice for an order.
     *
     * GET /api/v1/me/orders/{order}/invoice
     * Available once the order is confirmed, dispatched, or delivered.
     */
    public function invoice(Request $request, Order $order): Response
    {
        abort_if($order->user_id !== $request->user()->id, 403, 'Forbidden.');

        if (! in_array($order->order_status, ['confirmed', 'dispatched', 'delivered'])) {
            abort(422, 'Invoice is not available for orders with status: '.$order->order_status);
        }

        $order->load(['vendor:id,business_name,gstin,city,state', 'items']);

        $pdf = Pdf::loadView('pdf.invoice', ['order' => $order])
            ->setPaper('a4', 'portrait');

        $filename = 'invoice-'.$order->order_number.'.pdf';

        return $pdf->download($filename);
    }
}
