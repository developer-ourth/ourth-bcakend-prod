<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * GET /api/v1/products
     * Public — list active products with optional category/search filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'vendor', 'packs'])
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
        $product->load(['category', 'vendor', 'packs']);

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
            'wholesale_price' => 'nullable|numeric|min:0',
            'min_order_quantity' => 'nullable|integer|min:1',
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
            'packs' => 'nullable|array',
            'packs.*.name' => 'required|string|max:255',
            'packs.*.base_price' => 'required|numeric|min:0',
            'packs.*.discounted_price' => 'nullable|numeric|min:0',
            'packs.*.sku' => 'nullable|string|max:100',
            'packs.*.stock_quantity' => 'nullable|integer|min:0',
            'packs.*.is_active' => 'boolean',
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
        
        $packsData = Arr::pull($validated, 'packs', []);
        $validated = $this->normalizeImagesPayload($validated);

        $product = Product::create($validated);

        if (!empty($packsData)) {
            foreach ($packsData as $packData) {
                $packData['sku'] = $packData['sku'] ?? $product->sku.'-'.strtoupper(Str::random(4));
                $product->packs()->create($packData);
            }
        }

        $product->load(['category', 'vendor', 'packs']);

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
            'wholesale_price' => 'nullable|numeric|min:0',
            'min_order_quantity' => 'nullable|integer|min:1',
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
            'packs' => 'nullable|array',
            'packs.*.id' => 'nullable|integer|exists:product_packs,id',
            'packs.*.name' => 'required|string|max:255',
            'packs.*.base_price' => 'required|numeric|min:0',
            'packs.*.discounted_price' => 'nullable|numeric|min:0',
            'packs.*.sku' => 'nullable|string|max:100',
            'packs.*.stock_quantity' => 'nullable|integer|min:0',
            'packs.*.is_active' => 'boolean',
        ]);

        if (empty($validated['category']) && ! empty($validated['category_id'])) {
            $validated['category'] = Category::find($validated['category_id'])?->name ?? $product->category;
        }

        $packsData = Arr::pull($validated, 'packs');
        $validated = $this->normalizeImagesPayload($validated, $product->primary_image_url);

        $product->update($validated);

        if ($request->has('packs')) {
            $keepPackIds = [];
            if (is_array($packsData)) {
                foreach ($packsData as $packData) {
                    if (!empty($packData['id'])) {
                        $pack = $product->packs()->findOrFail($packData['id']);
                        $pack->update($packData);
                        $keepPackIds[] = $pack->id;
                    } else {
                        $packData['sku'] = $packData['sku'] ?? $product->sku.'-'.strtoupper(Str::random(4));
                        $pack = $product->packs()->create($packData);
                        $keepPackIds[] = $pack->id;
                    }
                }
            }
            $product->packs()->whereNotIn('id', $keepPackIds)->delete();
        }

        $product->load(['category', 'vendor', 'packs']);

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
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeImagesPayload(array $payload, ?string $fallbackPrimaryImage = null): array
    {
        if (! Arr::has($payload, 'secondary_images')) {
            return $payload;
        }

        $primaryImage = isset($payload['primary_image_url']) && is_string($payload['primary_image_url'])
            ? trim($payload['primary_image_url'])
            : trim((string) $fallbackPrimaryImage);

        $secondaryImages = collect(Arr::get($payload, 'secondary_images', []))
            ->filter(fn ($url): bool => is_string($url) && trim($url) !== '')
            ->map(fn (string $url): string => trim($url))
            ->reject(fn (string $url): bool => $primaryImage !== '' && $url === $primaryImage)
            ->unique()
            ->values()
            ->all();

        $payload['secondary_images'] = $secondaryImages;

        if (($primaryImage === '' || $primaryImage === null) && ! empty($secondaryImages)) {
            $payload['primary_image_url'] = $secondaryImages[0];
            $payload['secondary_images'] = array_values(array_slice($secondaryImages, 1));
        }

        return $payload;
    }
}
