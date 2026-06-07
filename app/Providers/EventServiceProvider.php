<?php

namespace App\Providers;

use App\Events\VendorRegistered;
use App\Events\VendorApproved;
use App\Events\VendorRejected;
use App\Listeners\SendWelcomeNotificationOnVendorRegistered;
use App\Listeners\GenerateQRCodeOnApproval;
use App\Listeners\SendApprovalNotificationOnVendorApproved;
use App\Listeners\SendRejectionNotificationOnVendorRejected;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * EventServiceProvider
 *
 * Registers events and their listeners.
 * This creates a loosely-coupled architecture where services can be
 * added or removed independently.
 *
 * In Phase 2: Listeners can be extracted to separate microservices
 * by implementing message queue consumers (Kafka, RabbitMQ, etc.)
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        VendorRegistered::class => [
            SendWelcomeNotificationOnVendorRegistered::class,
        ],
        VendorApproved::class => [
            GenerateQRCodeOnApproval::class,
            SendApprovalNotificationOnVendorApproved::class,
        ],
        VendorRejected::class => [
            SendRejectionNotificationOnVendorRejected::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }
}
