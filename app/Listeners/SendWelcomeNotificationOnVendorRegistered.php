<?php

namespace App\Listeners;

use App\Events\VendorRegistered;
use App\Jobs\SendNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * SendWelcomeNotificationOnVendorRegistered Listener
 *
 * When vendor registers, send welcome email asynchronously.
 */
class SendWelcomeNotificationOnVendorRegistered implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event
     */
    public function handle(VendorRegistered $event): void
    {
        dispatch(new SendNotificationJob('vendor.registration.confirmation', [
            'user' => $event->user,
        ]))->onQueue('notifications');
    }
}
