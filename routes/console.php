<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatically run abandoned cart WhatsApp recovery scanner every 15 minutes
Schedule::command('carts:send-abandoned-reminders')->everyFifteenMinutes();
