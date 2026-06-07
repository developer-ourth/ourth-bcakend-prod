<?php

namespace App\Listeners;

use App\Events\VendorApproved;
use App\Jobs\SendNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * SendApprovalNotificationOnVendorApproved Listener
 *
 * When vendor is approved, send approval notification asynchronously.
 */
class SendApprovalNotificationOnVendorApproved implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event
     */
    public function handle(VendorApproved $event): void
    {
        dispatch(new SendNotificationJob('vendor.approved', [
            'user' => $event->vendor->user,
        ]))->onQueue('notifications');
    }
}
