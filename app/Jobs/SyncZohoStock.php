<?php

namespace App\Jobs;

use App\Services\Zoho\ZohoInventoryClient;
use App\Services\Zoho\ZohoStockSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * "Sync stock now" from the admin dashboard. The scheduled sync runs the same
 * service through the zoho:sync-stock command.
 */
class SyncZohoStock implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public function handle(ZohoInventoryClient $zoho, ZohoStockSync $sync): void
    {
        if (! $zoho->isConfigured()) {
            return;
        }

        try {
            $sync->run();
        } catch (\Throwable $e) {
            ZohoStockSync::recordFailure($e);

            throw $e;
        }
    }
}
