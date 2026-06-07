<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * VendorDiscoveryController
 *
 * Public-facing vendor and product browsing for the consumer mobile app.
 * Only KYC-approved, active vendors are surfaced.
 */
class VendorDiscoveryController extends Controller
{
    /**
     * List vendors, optionally filtered by proximity and category.
     *
     * GET /api/v1/vendors
     * Query: lat, lng, radius (km, default 10), category, page, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Vendor::select([
            'id', 'user_id', 'business_name', 'business_category',
            'description', 'logo_url', 'city', 'state',
            'latitude', 'longitude', 'average_rating', 'total_ratings_count',
        ])
            ->where('kyc_status', 'verified')
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)]);

        if ($request->filled('category')) {
            $query->where('business_category', $request->category);
        }

        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = (float) $request->lat;
            $lng = (float) $request->lng;
            $radius = (float) ($request->radius ?? 10);

            // Haversine formula to filter by radius
            $query->selectRaw(
                '( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) )
                 * cos( radians( longitude ) - radians(?) )
                 + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance',
                [$lat, $lng, $lat]
            )
                ->having('distance', '<=', $radius)
                ->orderBy('distance');
        } else {
            $query->orderBy('average_rating', 'desc');
        }

        $vendors = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $vendors->items(),
            'meta' => [
                'current_page' => $vendors->currentPage(),
                'last_page' => $vendors->lastPage(),
                'total' => $vendors->total(),
                'per_page' => $vendors->perPage(),
            ],
        ]);
    }

    /**
     * Vendor detail with active product catalogue.
     *
     * GET /api/v1/vendors/{vendor}
     */
    public function show(Vendor $vendor): JsonResponse
    {
        if ($vendor->kyc_status !== 'verified') {
            return response()->json(['success' => false, 'message' => 'Vendor not found.'], 404);
        }

        $vendor->load([
            'products' => fn ($q) => $q->where('is_active', true)
                ->select(['id', 'vendor_id', 'name', 'description', 'category', 'base_price', 'discounted_price', 'primary_image_url', 'is_featured'])
                ->with('inventory:id,product_id,current_stock'),
        ]);

        return response()->json([
            'success' => true,
            'data' => $vendor,
        ]);
    }

    /**
     * Search products across all approved vendors.
     *
     * GET /api/v1/products
     * Query: q (search term), category, vendor_id, min_price, max_price, per_page
     */
    public function products(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Product::select([
            'id', 'vendor_id', 'category_id', 'name', 'description', 'category',
            'base_price', 'discounted_price', 'primary_image_url', 'is_featured',
        ])
            ->where('is_active', true)
            ->whereHas('vendor', fn ($q) => $q->where('kyc_status', 'verified'))
            ->with([
                'vendor:id,business_name,city,average_rating',
                'inventory:id,product_id,current_stock',
            ]);

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        } elseif ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->filled('min_price')) {
            $query->where('base_price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('base_price', '<=', $request->max_price);
        }

        $products = $query->orderByDesc('is_featured')->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
                'per_page' => $products->perPage(),
            ],
        ]);
    }
}
