<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RewardTransaction;
use App\Models\Subscription;
use App\Models\SustainabilityScore;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ConsumerDashboardController - Consumer Demand & Awareness Dashboard
 *
 * Provides eco-score, rewards, subscriptions, nearby vendors, and order history.
 */
class ConsumerDashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard/consumer/{user}
     */
    public function index(Request $request, User $user): JsonResponse
    {
        $score = SustainabilityScore::firstOrNew(['user_id' => $user->id]);

        $recentOrders = $user->phone
            ? Order::where('delivery_phone', $user->phone)
                ->latest()
                ->limit(5)
                ->get(['id', 'order_number', 'order_status', 'total_amount', 'created_at'])
            : collect();

        $recentRewards = RewardTransaction::where('user_id', $user->id)
            ->with('rewardCatalog:id,name,reward_type')
            ->latest()
            ->limit(5)
            ->get(['id', 'transaction_type', 'points', 'points_balance_after', 'description', 'created_at']);

        $activeSubscriptions = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['vendor:id,business_name', 'items.product:id,name,primary_image_url'])
            ->get();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'sustainability_score' => [
                'green_points' => $score->green_points,
                'carbon_points' => $score->carbon_points,
                'total_points' => $score->total_points,
                'tier' => $score->tier ?? 'bronze',
                'plastic_avoided_kg' => $score->plastic_avoided_kg ?? 0,
                'co2_saved_kg' => $score->co2_saved_kg ?? 0,
                'eco_orders_count' => $score->eco_orders_count ?? 0,
            ],
            'recent_orders' => $recentOrders,
            'recent_rewards' => $recentRewards,
            'active_subscriptions' => $activeSubscriptions->map(fn ($sub) => [
                'id' => $sub->id,
                'plan_name' => $sub->plan_name,
                'frequency' => $sub->frequency,
                'plan_price' => $sub->plan_price,
                'next_delivery_date' => $sub->next_delivery_date,
                'vendor_name' => $sub->vendor?->business_name,
                'items' => $sub->items->map(fn ($item) => [
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ]),
            ]),
        ]);
    }

    /**
     * GET /api/v1/dashboard/consumer/{user}/nearby-vendors
     *
     * Returns vendors near a given lat/lng within a radius.
     */
    public function nearbyVendors(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'radius_km' => ['sometimes', 'numeric', 'min:1', 'max:50'],
        ]);

        $lat = (float) $request->latitude;
        $lng = (float) $request->longitude;
        $radius = (float) $request->query('radius_km', 10);

        $vendors = Vendor::where('kyc_status', 'verified')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw(
                '*, ( 6371 * acos( cos( radians(?) ) * cos( radians(latitude) ) * cos( radians(longitude) - radians(?) ) + sin( radians(?) ) * sin( radians(latitude) ) ) ) AS distance_km',
                [$lat, $lng, $lat]
            )
            ->having('distance_km', '<=', $radius)
            ->orderBy('distance_km')
            ->limit(20)
            ->get(['id', 'business_name', 'business_category', 'latitude', 'longitude', 'average_rating', 'city']);

        return response()->json([
            'latitude' => $lat,
            'longitude' => $lng,
            'radius_km' => $radius,
            'vendors' => $vendors,
        ]);
    }

    /**
     * GET /api/v1/dashboard/consumer/{user}/rewards-summary
     */
    public function rewardsSummary(Request $request, User $user): JsonResponse
    {
        $score = SustainabilityScore::where('user_id', $user->id)->first();

        $pointsEarned = RewardTransaction::where('user_id', $user->id)
            ->where('transaction_type', 'earn')
            ->sum('points');

        $pointsRedeemed = RewardTransaction::where('user_id', $user->id)
            ->where('transaction_type', 'redeem')
            ->sum('points');

        $recentHistory = RewardTransaction::where('user_id', $user->id)
            ->with('rewardCatalog:id,name,reward_type')
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'current_balance' => $score?->total_points ?? 0,
            'tier' => $score?->tier ?? 'bronze',
            'points_earned_lifetime' => $pointsEarned,
            'points_redeemed_lifetime' => $pointsRedeemed,
            'history' => $recentHistory,
        ]);
    }
}
