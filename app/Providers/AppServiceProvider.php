<?php

namespace App\Providers;

use App\Contracts\QRCodeGeneratorContract;
use App\Contracts\NotificationServiceContract;
use App\Contracts\KYCProcessorContract;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\Inventory;
use App\Models\Order;
use App\Observers\DeliveryObserver;
use App\Observers\DeliveryRouteObserver;
use App\Observers\InventoryObserver;
use App\Observers\OrderObserver;
use App\Services\QRCodeService;
use App\Services\NotificationService;
use App\Services\KYCService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * This is where service contracts are bound to implementations.
     *
     * Phase 1: Bind to local service classes
     * Phase 2: Bind to HTTP clients that call microservices
     */
    public function register(): void
    {
        // Register service contracts
        // These bindings allow easy swapping of implementations

        // QR Code Service - Can be swapped to microservice in Phase 2
        $this->app->bind(
            QRCodeGeneratorContract::class,
            QRCodeService::class
        );

        // Notification Service - Can be swapped to dedicated notification microservice
        $this->app->bind(
            NotificationServiceContract::class,
            NotificationService::class
        );

        // KYC Processor - Can be swapped to ML-based validation microservice
        $this->app->bind(
            KYCProcessorContract::class,
            KYCService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        Order::observe(OrderObserver::class);
        Delivery::observe(DeliveryObserver::class);
        DeliveryRoute::observe(DeliveryRouteObserver::class);
        Inventory::observe(InventoryObserver::class);
    }
}
