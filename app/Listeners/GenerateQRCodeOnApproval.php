<?php

namespace App\Listeners;

use App\Events\VendorApproved;
use App\Jobs\GenerateVendorQRCodeJob;
use App\Jobs\SendNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * GenerateQRCodeOnApproval Listener
 *
 * When vendor is approved, automatically generate their QR code.
 * This keeps the workflow automatic and loosely coupled.
 */
class GenerateQRCodeOnApproval implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event
     */
    public function handle(VendorApproved $event): void
    {
        // Dispatch QR generation as background job
        dispatch(new GenerateVendorQRCodeJob($event->vendor))
            ->onQueue('default')
            ->delay(now()->addSeconds(5));
    }
}
