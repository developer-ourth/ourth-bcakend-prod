<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consumer\CreateSubscriptionRequest;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * SubscriptionController
 *
 * Manage recurring product subscriptions for the authenticated consumer.
 */
class SubscriptionController extends Controller
{
    /**
     * List all subscriptions for the authenticated consumer.
     *
     * GET /api/v1/me/subscriptions
     */
    public function index(Request $request): JsonResponse
    {
        $subscriptions = Subscription::where('user_id', $request->user()->id)
            ->with([
                'vendor:id,business_name,logo_url',
                'items.product:id,name,primary_image_url',
            ])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $subscriptions]);
    }

    /**
     * Get a single subscription detail.
     *
     * GET /api/v1/me/subscriptions/{subscription}
     */
    public function show(Request $request, Subscription $subscription): JsonResponse
    {
        abort_if($subscription->user_id !== $request->user()->id, 403, 'Forbidden.');

        $subscription->load(['vendor:id,business_name,logo_url,city', 'items.product']);

        return response()->json(['success' => true, 'data' => $subscription]);
    }

    /**
     * Create a new subscription.
     *
     * POST /api/v1/me/subscriptions
     */
    public function store(CreateSubscriptionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user      = $request->user();

        $subscription = Subscription::create([
            'user_id'          => $user->id,
            'vendor_id'        => $validated['vendor_id'],
            'plan_name'        => $validated['plan_name'],
            'frequency'        => $validated['frequency'],
            'status'           => 'active',
            'plan_price'       => $validated['plan_price'],
            'start_date'       => $validated['start_date'],
            'next_delivery_date' => $validated['start_date'],
            'delivery_address' => $validated['delivery_address'],
            'total_deliveries' => 0,
            'deliveries_completed' => 0,
        ]);

        foreach ($validated['items'] as $item) {
            SubscriptionItem::create([
                'subscription_id' => $subscription->id,
                'product_id'      => $item['product_id'],
                'quantity'        => $item['quantity'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription created successfully.',
            'data'    => $subscription->load(['items.product:id,name', 'vendor:id,business_name']),
        ], 201);
    }

    /**
     * Pause or cancel a subscription.
     *
     * PATCH /api/v1/me/subscriptions/{subscription}
     * Body: { "action": "pause" | "resume" | "cancel", "reason": "..." }
     */
    public function update(Request $request, Subscription $subscription): JsonResponse
    {
        abort_if($subscription->user_id !== $request->user()->id, 403, 'Forbidden.');

        $request->validate([
            'action' => ['required', Rule::in(['pause', 'resume', 'cancel'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($subscription->status === 'cancelled') {
            return response()->json(['success' => false, 'message' => 'Subscription is already cancelled.'], 422);
        }

        $updates = match ($request->action) {
            'pause'  => ['status' => 'paused'],
            'resume' => ['status' => 'active'],
            'cancel' => [
                'status'              => 'cancelled',
                'cancelled_at'        => now(),
                'cancellation_reason' => $request->reason,
            ],
        };

        $subscription->update($updates);

        return response()->json([
            'success' => true,
            'message' => ucfirst($request->action).'d subscription successfully.',
            'data'    => $subscription->fresh(),
        ]);
    }
}
