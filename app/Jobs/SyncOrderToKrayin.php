<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\KrayinClient;
use App\Services\LeadCaptureService;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * On a paid order: moves the customer's Krayin lead to "Won" and logs the
 * order on its timeline. Customers with no lead yet get one created directly
 * in the Won stage, so the sales team sees every buyer in the CRM.
 */
class SyncOrderToKrayin implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $orderId) {}

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(KrayinClient $krayin): void
    {
        if (! $krayin->isConfigured()) {
            return;
        }

        $order = Order::with('user')->find($this->orderId);
        $user = $order?->user;

        if (! $user) {
            return;
        }

        // Razorpay can deliver order.paid more than once.
        if (! Cache::add("krayin-order-synced:{$order->id}", true, now()->addDays(30))) {
            return;
        }

        try {
            Cache::lock("krayin-lead-sync:{$user->id}", 60)->block(30, function () use ($krayin, $order, $user) {
                $amount = number_format((float) $order->total_amount, 2);
                $note = "Order {$order->order_number} paid: ₹{$amount}.";

                if ($user->krayin_lead_id) {
                    $krayin->moveToStage($user->krayin_lead_id, 'won');
                    $krayin->addNote($user->krayin_lead_id, $note);

                    return;
                }

                $phone = PhoneNumber::toE164Digits($user->phone ?: $order->delivery_phone);
                $phone = $phone ? '+' . $phone : null;

                $ids = $krayin->createLead(
                    title: ($user->name ?: 'Customer') . ' – Order ' . $order->order_number,
                    person: [
                        'name' => $user->name ?: 'Customer',
                        'phone' => $phone,
                        'email' => $user->email,
                        'person_id' => $user->krayin_person_id ?: $krayin->findPersonId($phone, $user->email),
                    ],
                    sourceName: LeadCaptureService::KRAYIN_SOURCES['website_order'],
                    description: $note,
                    value: (float) $order->total_amount,
                    stageCode: 'won',
                );

                $user->forceFill([
                    'krayin_lead_id' => $ids['lead_id'],
                    'krayin_person_id' => $ids['person_id'] ?? $user->krayin_person_id,
                ])->save();
            });
        } catch (\Throwable $e) {
            Cache::forget("krayin-order-synced:{$order->id}");

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Krayin order sync failed for order #{$this->orderId}: " . $e->getMessage());
    }
}
