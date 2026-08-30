<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;

echo "=== LATEST 10 ORDERS IN DATABASE ===\n";
$orders = Order::orderBy('id', 'desc')->take(10)->get();

foreach ($orders as $o) {
    echo "ID: #{$o->id} | Num: {$o->order_number} | Status: {$o->order_status} | PayStatus: {$o->payment_status} | Method: " . ($o->payment?->payment_method ?? 'N/A') . " | AWB: " . ($o->awb_number ?: 'NONE') . " | Created: {$o->created_at}\n";
}
