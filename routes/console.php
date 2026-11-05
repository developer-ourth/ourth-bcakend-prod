<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatically run abandoned cart WhatsApp recovery scanner every 15 minutes
Schedule::command('carts:send-abandoned-reminders')->everyFifteenMinutes();

// Zoho Inventory is the source of truth for stock: pull it onto the website
Schedule::command('zoho sync-stock')
    ->cron('*/' . max(5, (int) config('services.zoho.stock_sync_minutes', 10)) . ' * * * *')
    ->withoutOverlapping(15)
    ->when(fn () => app(\App\Services\Zoho\ZohoInventoryClient::class)->isConfigured());

// Safety net: any paid order that didn't reach Zoho (e.g. Zoho was down) is retried hourly
Schedule::command('zoho push-pending')
    ->hourly()
    ->when(fn () => app(\App\Services\Zoho\ZohoInventoryClient::class)->isConfigured());
