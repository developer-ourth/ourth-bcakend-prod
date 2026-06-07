<?php

namespace App\Providers;

use App\Contracts\QRCodeGeneratorContract;
use App\Contracts\NotificationServiceContract;
use App\Contracts\KYCProcessorContract;
use App\Services\QRCodeService;
use App\Services\NotificationService;
use App\Services\KYCService;
use Illuminate\Support\ServiceProvider;

/**
 * ServiceProvider for binding service contracts to implementations
 *
 * This allows swapping implementations without changing application code.
 *
 * Phase 1: Bindings point to local service classes
 * Phase 2: Can bind to HTTP clients that call microservices
 *
 * Example Phase 2 binding:
 * $this->bind(QRCodeGeneratorContract::class, function () {
 *     return new QRCodeServiceClient('http://qr-service:3000');
 * });
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind service contracts to implementations
        $this->app->bind(
            QRCodeGeneratorContract::class,
            QRCodeService::class
        );

        $this->app->bind(
            NotificationServiceContract::class,
            NotificationService::class
        );

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
        //
    }
}
