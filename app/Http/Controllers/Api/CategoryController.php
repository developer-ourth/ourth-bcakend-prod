<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * GET /api/v1/categories
     * Public — list all active top-level categories with their children.
     */
    public function index(): JsonResponse
    {
        $categories = Category::with('children')
            ->withCount('products')
            ->active()
            ->topLevel()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories),
        ]);
    }

    /**
     * POST /api/v1/admin/categories
     * Admin — create a category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'icon_url' => 'nullable|url',
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Ensure slug uniqueness
        $base = $validated['slug'];
        $i = 1;
        while (Category::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $base.'-'.$i++;
        }

        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category created.',
            'data' => new CategoryResource($category),
        ], 201);
    }

    /**
     * PUT /api/v1/admin/categories/{category}
     * Admin — update a category.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'icon_url' => 'nullable|url',
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category updated.',
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * DELETE /api/v1/admin/categories/{category}
     * Admin — delete a category (only if no products assigned).
     */
    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a category that has products. Reassign products first.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted.',
        ]);
    }
}
