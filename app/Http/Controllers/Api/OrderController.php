<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * OrderController - Order Management API
 *
 * Handles order creation, listing, updates, and status tracking.
 *
 * Order Workflow:
 * 1. Create order from cart → creates order + order items
 * 2. Admin confirms/rejects → updates order_status
 * 3. Admin dispatches → updates order_status to 'dispatched'
 * 4. Delivery updates → updates delivery tracking
 * 5. Admin generates invoice → creates invoice record
 */
class OrderController extends Controller
{
    /**
     * Get list of all orders (admin only)
     *
     * GET /api/v1/orders
     *
     * Query parameters:
     * - page=1
     * - per_page=15
     * - status=pending|confirmed|dispatched|delivered|cancelled
     * - vendor_id=1
     * - date_from=2026-04-01
     * - date_to=2026-04-30
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 15);
        $status = $request->query('status', null);
        $vendorId = $request->query('vendor_id', null);

        $query = Order::select([
            'id',
            'order_number',
            'uuid',
            'vendor_id',
            'order_status',
            'payment_status',
            'total_amount',
            'created_at',
            'confirmed_at',
            'dispatched_at',
            'delivered_at',
        ])
            ->with(['vendor:id,business_name', 'items']);

        if ($status && in_array($status, ['pending', 'confirmed', 'processing', 'out_for_delivery', 'delivered', 'cancelled'])) {
            $query->where('order_status', $status);
        }

        if ($vendorId) {
            $query->where('vendor_id', $vendorId);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $orders->getCollection()->transform(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'vendor_name' => $order->vendor?->business_name,
                'order_status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'total_amount' => $order->total_amount,
                'created_at' => $order->created_at,
                'items_count' => $order->items?->count() ?? 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * Get single order details
     *
     * GET /api/v1/orders/{order}
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['vendor', 'items', 'delivery', 'payment']);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Create a new order
     *
     * POST /api/v1/orders
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'delivery_address_line1' => 'required|string|max:255',
            'delivery_city' => 'required|string|max:100',
            'delivery_state' => 'required|string|max:100',
            'delivery_postal_code' => 'required|string|max:10',
            'delivery_country' => 'required|string|max:100',
            'delivery_phone' => 'required|string|max:20',
            'customer_notes' => 'nullable|string|max:500',
        ]);

        try {
            $subtotal = 0;

            $order = Order::create([
                'order_number' => 'ORD-'.now()->format('Y').'-'.str_pad(Order::count() + 1, 6, '0', STR_PAD_LEFT),
                'uuid' => Str::uuid(),
                'vendor_id' => $validated['vendor_id'],
                'order_status' => 'pending',
                'payment_status' => 'pending',
                'delivery_address_line1' => $validated['delivery_address_line1'],
                'delivery_city' => $validated['delivery_city'],
                'delivery_state' => $validated['delivery_state'],
                'delivery_postal_code' => $validated['delivery_postal_code'],
                'delivery_country' => $validated['delivery_country'],
                'delivery_phone' => $validated['delivery_phone'],
                'customer_notes' => $validated['customer_notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $subtotal += $itemTotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $itemTotal,
                ]);
            }

            $order->update([
                'subtotal' => $subtotal,
                'total_amount' => $subtotal,
            ]);

            $order->load('items');

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order creation failed: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Confirm order (Admin only)
     *
     * POST /api/v1/orders/{order}/confirm
     */
    public function confirm(Request $request, Order $order): JsonResponse
    {
        if ($order->order_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending orders can be confirmed',
            ], 400);
        }

        try {
            $order->update([
                'order_status' => 'confirmed',
                'confirmed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order confirmed',
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Confirmation failed: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Mark order as processing / Ready Box (Admin only)
     *
     * POST /api/v1/orders/{order}/process
     */
    public function process(Request $request, Order $order): JsonResponse
    {
        if ($order->order_status !== 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => 'Only confirmed orders can be marked as ready to box',
            ], 400);
        }

        try {
            $order->update([
                'order_status' => 'processing',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order marked as ready to box',
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Process update failed: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Dispatch order — mark as Out for Delivery (Admin only)
     *
     * POST /api/v1/orders/{order}/dispatch
     */
    public function dispatch(Request $request, Order $order): JsonResponse
    {
        if ($order->order_status !== 'processing') {
            return response()->json([
                'success' => false,
                'message' => 'Only processing (ready box) orders can be dispatched',
            ], 400);
        }

        try {
            $order->update([
                'order_status' => 'out_for_delivery',
                'dispatched_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order is out for delivery',
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dispatch failed: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel order (Admin only)
     *
     * POST /api/v1/orders/{order}/cancel
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if (in_array($order->order_status, ['delivered', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel '.$order->order_status.' orders',
            ], 400);
        }

        try {
            $order->update([
                'order_status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $validated['reason'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled',
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cancellation failed: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Mark order as delivered (Admin only)
     *
     * POST /api/v1/orders/{order}/deliver
     */
    public function deliver(Request $request, Order $order): JsonResponse
    {
        if ($order->order_status !== 'out_for_delivery') {
            return response()->json([
                'success' => false,
                'message' => 'Only orders out for delivery can be marked as delivered',
            ], 400);
        }

        try {
            $order->update([
                'order_status' => 'delivered',
                'delivered_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order marked as delivered',
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery update failed: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get order statistics (Admin only)
     *
     * GET /api/v1/orders/stats
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => Order::count(),
                'pending' => Order::where('order_status', 'pending')->count(),
                'confirmed' => Order::where('order_status', 'confirmed')->count(),
                'dispatched' => Order::where('order_status', 'dispatched')->count(),
                'delivered' => Order::where('order_status', 'delivered')->count(),
                'cancelled' => Order::where('order_status', 'cancelled')->count(),
                'total_revenue' => Order::where('order_status', 'delivered')->sum('total_amount'),
            ],
        ]);
    }
}
