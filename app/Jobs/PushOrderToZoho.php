<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Zoho\ZohoApiException;
use App\Services\Zoho\ZohoInventoryClient;
use App\Services\Zoho\ZohoOrderSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Paid order → Zoho Inventory (sales order, invoice, payment). Dispatched from
 * both the Razorpay webhook and the in-app payment verification, so it is
 * unique per order and every step inside is idempotent.
 */
class PushOrderToZoho implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 6;

    public int $uniqueFor = 600;

    public function __construct(public int $orderId, public bool $force = false) {}

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600, 7200];
    }

    public function handle(ZohoInventoryClient $zoho, ZohoOrderSync $sync): void
    {
        $order = Order::find($this->orderId);

        if (! $zoho->isConfigured() || ! $order || ! ZohoOrderSync::shouldSync($order)) {
            return;
        }

        if ($order->zoho_synced_at && ! $this->force) {
            return; // already fully synced
        }

        try {
            $sync->push($order);
        } catch (\Throwable $e) {
            $order->forceFill(['zoho_sync_error' => mb_substr($e->getMessage(), 0, 1000)])->save();

            // A missing SKU won't fix itself by retrying; wait for someone to fix it and press Retry.
            if (! $e instanceof ZohoApiException && str_contains($e->getMessage(), 'No Zoho item with SKU')) {
                $this->fail($e);

                return;
            }

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Zoho order sync failed for order #{$this->orderId}: " . $e->getMessage());
    }
}
