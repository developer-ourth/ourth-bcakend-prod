<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consumer\RedeemRewardRequest;
use App\Models\RewardCatalog;
use App\Models\RewardTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RewardController
 *
 * Rewards wallet: points balance, history, catalogue, and redemption.
 */
class RewardController extends Controller
{
    /**
     * Get the consumer's points balance and recent transaction history.
     *
     * GET /api/v1/me/rewards
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $latestTransaction = RewardTransaction::where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        $pointsBalance = $latestTransaction?->points_balance_after ?? 0;

        $history = RewardTransaction::where('user_id', $user->id)
            ->with('rewardCatalog:id,name')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => [
                'points_balance' => $pointsBalance,
                'history'        => $history->items(),
                'meta'           => [
                    'current_page' => $history->currentPage(),
                    'last_page'    => $history->lastPage(),
                    'total'        => $history->total(),
                ],
            ],
        ]);
    }

    /**
     * List available rewards in the catalogue.
     *
     * GET /api/v1/me/rewards/catalog
     */
    public function catalog(): JsonResponse
    {
        $rewards = RewardCatalog::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()))
            ->where(fn ($q) => $q->whereNull('total_quantity')->orWhereColumn('redeemed_count', '<', 'total_quantity'))
            ->orderBy('points_required')
            ->get();

        return response()->json(['success' => true, 'data' => $rewards]);
    }

    /**
     * Redeem points for a reward.
     *
     * POST /api/v1/me/rewards/redeem
     */
    public function redeem(RedeemRewardRequest $request): JsonResponse
    {
        $user   = $request->user();
        $reward = RewardCatalog::findOrFail($request->reward_catalog_id);

        if (! $reward->isAvailable()) {
            return response()->json(['success' => false, 'message' => 'This reward is no longer available.'], 422);
        }

        $currentBalance = RewardTransaction::where('user_id', $user->id)
            ->orderByDesc('id')
            ->value('points_balance_after') ?? 0;

        if ($currentBalance < $reward->points_required) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient points. You have {$currentBalance}, need {$reward->points_required}.",
            ], 422);
        }

        $transaction = DB::transaction(function () use ($user, $reward, $currentBalance) {
            $newBalance = $currentBalance - $reward->points_required;

            $transaction = RewardTransaction::create([
                'user_id'              => $user->id,
                'reward_catalog_id'    => $reward->id,
                'transaction_type'     => 'debit',
                'points'               => $reward->points_required,
                'points_balance_after' => $newBalance,
                'source'               => 'redemption',
                'source_reference'     => $reward->name,
                'description'          => "Redeemed: {$reward->name}",
                'expires_at'           => now()->addDays(30),
            ]);

            $reward->increment('redeemed_count');

            return $transaction;
        });

        return response()->json([
            'success' => true,
            'message' => 'Reward redeemed successfully.',
            'data'    => [
                'transaction'      => $transaction,
                'new_balance'      => $transaction->points_balance_after,
                'reward'           => $reward->only(['id', 'name', 'reward_type', 'cashback_amount', 'discount_percent']),
            ],
        ]);
    }
}
