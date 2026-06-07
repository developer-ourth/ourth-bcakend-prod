<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * GET /api/v1/products
     * Public — list active products with optional category/search filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'vendor'])
            ->active()
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                    ->orWhere('description', 'ilike', "%{$term}%");
            });
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        $products = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products->items()),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/products/{product}
     * Public — single product detail.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'vendor']);

        return response()->json([
            'success' => true,
            'data' => new ProductResource($product),
        ]);
    }

    /**
     * POST /api/v1/admin/products
     * Admin — create a new product.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'category' => 'nullable|string|max:100',
            'sub_category' => 'nullable|string|max:100',
            'base_price' => 'required|numeric|min:0',
            'discounted_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100',
            'primary_image_url' => 'nullable|url',
            'secondary_images' => 'nullable|array',
            'secondary_images.*' => 'url',
            'weight_grams' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:30',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'vendor_id' => 'nullable|exists:vendors,id',
        ]);

        $validated['sku'] = $validated['sku'] ?? 'SKU-'.strtoupper(Str::random(8));

        // Auto-assign the distributor vendor when admin doesn't explicitly specify one
        if (empty($validated['vendor_id'])) {
            $distributor = Vendor::distributor();
            if ($distributor) {
                $validated['vendor_id'] = $distributor->id;
            }
        }

        if (empty($validated['category']) && ! empty($validated['category_id'])) {
            $validated['category'] = Category::find($validated['category_id'])?->name ?? 'General';
        }

        $validated['category'] = $validated['category'] ?? 'General';

        $product = Product::create($validated);
        $product->load(['category', 'vendor']);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => new ProductResource($product),
        ], 201);
    }

    /**
     * PUT /api/v1/admin/products/{product}
     * Admin — update a product.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'category' => 'nullable|string|max:100',
            'sub_category' => 'nullable|string|max:100',
            'base_price' => 'sometimes|required|numeric|min:0',
            'discounted_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'sku' => 'sometimes|required|string|max:100|unique:products,sku,'.$product->id,
            'barcode' => 'nullable|string|max:100',
            'primary_image_url' => 'nullable|url',
            'secondary_images' => 'nullable|array',
            'secondary_images.*' => 'url',
            'weight_grams' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:30',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ]);

        if (empty($validated['category']) && ! empty($validated['category_id'])) {
            $validated['category'] = Category::find($validated['category_id'])?->name ?? $product->category;
        }

        $product->update($validated);
        $product->load(['category', 'vendor']);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => new ProductResource($product),
        ]);
    }

    /**
     * DELETE /api/v1/admin/products/{product}
     * Admin — soft-delete a product.
     */
    public function destroy(Product $product): JsonResponse
    {
        if ($product->orderItems()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This product has existing orders and cannot be deleted. Mark it inactive instead.',
            ], 422);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }
}
