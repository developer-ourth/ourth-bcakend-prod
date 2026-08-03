<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consumer\AddToCartRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CartController
 *
 * Manages the authenticated consumer's shopping cart.
 * One active cart per user per vendor at a time.
 */
class CartController extends Controller
{
    /**
     * Get the authenticated user's active cart.
     *
     * GET /api/v1/me/cart
     */
    public function show(Request $request): JsonResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->with([
                'items' => fn ($q) => $q->with(['product:id,name,primary_image_url,base_price,discounted_price', 'productPack']),
                'vendor:id,business_name,logo_url,city',
                'coupon',
            ])
            ->first();

        if (! $cart) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Cart is empty.',
            ]);
        }

        return response()->json(['success' => true, 'data' => $cart]);
    }

    /**
     * Apply a coupon to the cart.
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);
        
        $coupon = Coupon::where('code', $request->code)->first();

        if (!$coupon || !$coupon->isValid()) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired coupon code.'], 400);
        }

        $cart = Cart::where('user_id', $request->user()->id)->where('status', 'active')->first();
        if (!$cart) {
            return response()->json(['success' => false, 'message' => 'Cart is empty.'], 400);
        }

        if ($coupon->product_id && !$cart->items()->where('product_id', $coupon->product_id)->exists()) {
            return response()->json(['success' => false, 'message' => 'This coupon is not valid for any items in your cart.'], 400);
        }

        $cart->coupon_id = $coupon->id;
        $cart->save();

        $this->recalculateCart($cart);

        return response()->json([
            'success' => true,
            'message' => 'Coupon applied successfully.',
            'data' => $cart->fresh(['items.product:id,name,primary_image_url', 'items.productPack', 'vendor:id,business_name', 'coupon']),
        ]);
    }

    /**
     * Remove applied coupon.
     */
    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)->where('status', 'active')->first();
        if ($cart) {
            $cart->coupon_id = null;
            $cart->save();
            $this->recalculateCart($cart);
        }
        
        return response()->json([
            'success' => true, 
            'message' => 'Coupon removed.', 
            'data' => $cart ? $cart->fresh(['items.product:id,name,primary_image_url', 'items.productPack', 'vendor:id,business_name', 'coupon']) : null
        ]);
    }

    /**
     * Add a product to the cart.
     * Creates the cart if none exists for this vendor.
     *
     * POST /api/v1/me/cart/items
     */
    public function addItem(AddToCartRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $product = Product::where('id', $validated['product_id'])
            ->where('is_active', true)
            ->firstOrFail();

        if (! $product->vendor_id) {
            return response()->json([
                'success' => false,
                'message' => 'This product is currently unavailable for checkout.',
            ], 422);
        }

        $packId = $validated['product_pack_id'] ?? null;
        $pack = null;
        if ($packId) {
            $pack = $product->packs()->where('id', $packId)->where('is_active', true)->first();
            if (!$pack) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected pack size is invalid or inactive.',
                ], 422);
            }
        }

        $existingCart = Cart::where('user_id', $user->id)->where('status', 'active')->first();

        // In the B2D model all products belong to the single Ourth distributor vendor.
        // If a stale cart exists with a mismatched or null vendor_id, just update it
        // rather than blocking the customer.
        if ($existingCart && $existingCart->vendor_id !== $product->vendor_id) {
            $existingCart->update(['vendor_id' => $product->vendor_id]);
        }

        $cart = $existingCart ?? Cart::create([
            'user_id' => $user->id,
            'vendor_id' => $product->vendor_id,
            'status' => 'active',
            'last_activity_at' => now(),
        ]);

        // Temporarily disabled for now: B2B and B2C use the same rate.
        $isB2B = false; // $user->role === 'vendor';
        $unitPrice = $pack 
            ? ($isB2B && $pack->wholesale_price !== null
                ? $pack->wholesale_price
                : ($pack->discounted_price ?? $pack->base_price))
            : ($isB2B && $product->wholesale_price !== null 
                ? $product->wholesale_price 
                : ($product->discounted_price ?? $product->base_price));

        $item = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_pack_id', $packId)
            ->first();

        if ($item) {
            $item->quantity += $validated['quantity'];
            $item->total_price = $item->quantity * $unitPrice;
            $item->save();
        } else {
            $item = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'product_pack_id' => $packId,
                'quantity' => $validated['quantity'],
                'unit_price' => $unitPrice,
                'total_price' => $validated['quantity'] * $unitPrice,
            ]);
        }

        $this->recalculateCart($cart);

        return response()->json([
            'success' => true,
            'message' => 'Item added to cart.',
            'data' => $cart->fresh(['items.product:id,name,primary_image_url', 'items.productPack', 'vendor:id,business_name']),
        ]);
    }

    /**
     * Update item quantity. Set to 0 to remove.
     *
     * PATCH /api/v1/me/cart/items/{item}
     */
    public function updateItem(Request $request, CartItem $item): JsonResponse
    {
        $this->authorizeCartItem($request, $item);

        $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:100000']]);

        if ($request->quantity === 0) {
            $item->delete();
        } else {
            $item->update([
                'quantity' => $request->quantity,
                'total_price' => $request->quantity * $item->unit_price,
            ]);
        }

        $this->recalculateCart($item->cart);

        return response()->json([
            'success' => true,
            'message' => 'Cart updated.',
            'data' => $item->cart->fresh(['items.product:id,name,primary_image_url', 'vendor:id,business_name']),
        ]);
    }

    /**
     * Remove a specific item from the cart.
     *
     * DELETE /api/v1/me/cart/items/{item}
     */
    public function removeItem(Request $request, CartItem $item): JsonResponse
    {
        $this->authorizeCartItem($request, $item);
        $cart = $item->cart;
        $item->delete();
        $this->recalculateCart($cart);

        return response()->json(['success' => true, 'message' => 'Item removed from cart.']);
    }

    /**
     * Clear the entire active cart.
     *
     * DELETE /api/v1/me/cart
     */
    public function clear(Request $request): JsonResponse
    {
        Cart::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->delete();

        return response()->json(['success' => true, 'message' => 'Cart cleared.']);
    }

    private function recalculateCart(Cart $cart): void
    {
        $cart->load('items', 'coupon');
        $subtotal = $cart->items->sum('total_price');
        $totalItems = $cart->items->sum('quantity');
        $discountAmount = 0;

        if ($cart->coupon && $cart->coupon->isValid()) {
            if ($cart->coupon->product_id) {
                // Apply discount only to specific product
                $eligibleItem = $cart->items->where('product_id', $cart->coupon->product_id)->first();
                if ($eligibleItem) {
                    $discountAmount = $eligibleItem->total_price * ($cart->coupon->discount_percentage / 100);
                }
            } else {
                // Apply discount to whole cart
                $discountAmount = $subtotal * ($cart->coupon->discount_percentage / 100);
            }
        } else {
            $cart->coupon_id = null;
        }

        $cart->update([
            'total_amount' => max(0, $subtotal - $discountAmount),
            'discount_amount' => $discountAmount,
            'total_items' => $totalItems,
            'last_activity_at' => now(),
        ]);
    }

    /** Assert the cart item belongs to the authenticated user. */
    private function authorizeCartItem(Request $request, CartItem $item): void
    {
        $item->load('cart');
        abort_if($item->cart->user_id !== $request->user()->id, 403, 'Forbidden.');
    }
}
