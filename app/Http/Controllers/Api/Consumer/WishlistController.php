<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WishlistController extends Controller
{
    /**
     * List all wishlist items for the authenticated user.
     *
     * GET /api/v1/me/wishlist
     */
    public function index(Request $request): JsonResponse
    {
        $items = Wishlist::where('user_id', $request->user()->id)
            ->with([
                'product:id,name,base_price,discounted_price,primary_image_url,vendor_id',
                'product.vendor:id,business_name,average_rating',
            ])
            ->get()
            ->pluck('product')
            ->filter();

        return response()->json(['success' => true, 'data' => $items->values()]);
    }

    /**
     * Add a product to the wishlist (idempotent).
     *
     * POST /api/v1/me/wishlist  { product_id: int }
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['product_id' => ['required', 'integer', 'exists:products,id']]);

        Wishlist::firstOrCreate([
            'user_id'    => $request->user()->id,
            'product_id' => $request->product_id,
        ]);

        return response()->json(['success' => true, 'message' => 'Added to wishlist.'], 201);
    }

    /**
     * Remove a product from the wishlist.
     *
     * DELETE /api/v1/me/wishlist/{product}
     */
    public function destroy(Request $request, Product $product): JsonResponse|Response
    {
        $deleted = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        if (! $deleted) {
            return response()->json(['success' => false, 'message' => 'Item not found in wishlist.'], 404);
        }

        return response()->noContent();
    }
}
