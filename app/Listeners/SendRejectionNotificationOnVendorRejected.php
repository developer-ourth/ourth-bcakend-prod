<?php

namespace App\Listeners;

use App\Events\VendorRejected;
use App\Jobs\SendNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * SendRejectionNotificationOnVendorRejected Listener
 *
 * When vendor is rejected, send rejection notification with reason.
 */
class SendRejectionNotificationOnVendorRejected implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event
     */
    public function handle(VendorRejected $event): void
    {
        $approval = $event->vendor->approval;

        dispatch(new SendNotificationJob('vendor.rejected', [
            'user' => $event->vendor->user,
            'reason' => $approval?->rejection_reason ?? 'Not specified',
        ]))->onQueue('notifications');
    }
}
