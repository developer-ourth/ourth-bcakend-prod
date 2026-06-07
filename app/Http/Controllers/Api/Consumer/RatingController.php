<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consumer\SubmitRatingRequest;
use App\Models\Product;
use App\Models\Rating;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;

/**
 * RatingController
 *
 * Submit ratings and reviews for vendors or products.
 */
class RatingController extends Controller
{
    /**
     * Submit a rating for a vendor or product.
     *
     * POST /api/v1/me/ratings
     */
    public function store(SubmitRatingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user      = $request->user();

        $modelMap = ['vendor' => Vendor::class, 'product' => Product::class];
        $modelClass = $modelMap[$validated['ratable_type']];

        abort_unless($modelClass::find($validated['ratable_id']), 404, 'Target not found.');

        // One rating per user per item
        $existing = Rating::where('ratable_type', $modelClass)
            ->where('ratable_id', $validated['ratable_id'])
            ->where('reviewer_id', $user->id)
            ->first();

        if ($existing) {
            $existing->update([
                'rating'        => $validated['rating'],
                'review'        => $validated['review'] ?? null,
                'review_photos' => $validated['review_photos'] ?? [],
            ]);

            return response()->json(['success' => true, 'message' => 'Rating updated.', 'data' => $existing]);
        }

        $rating = Rating::create([
            'ratable_type'  => $modelClass,
            'ratable_id'    => $validated['ratable_id'],
            'reviewer_id'   => $user->id,
            'rating'        => $validated['rating'],
            'review'        => $validated['review'] ?? null,
            'review_photos' => $validated['review_photos'] ?? [],
            'is_verified'   => false,
        ]);

        // Update vendor's aggregate rating if rating a vendor
        if ($validated['ratable_type'] === 'vendor') {
            $vendor = Vendor::find($validated['ratable_id']);
            $avg    = Rating::where('ratable_type', Vendor::class)->where('ratable_id', $vendor->id)->avg('rating');
            $count  = Rating::where('ratable_type', Vendor::class)->where('ratable_id', $vendor->id)->count();
            $vendor->update(['average_rating' => round($avg, 2), 'total_ratings_count' => $count]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rating submitted successfully.',
            'data'    => $rating,
        ], 201);
    }
}
