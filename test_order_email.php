<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;
use App\Observers\OrderObserver;
use App\Services\ExpoPushService;

echo "=== TESTING ORDER EMAIL NOTIFICATION ===\n";

$order = Order::orderBy('id', 'desc')->first();
if ($order) {
    echo "Triggering Observer for Order #{$order->id} ({$order->order_number})...\n";
    $observer = new OrderObserver(new ExpoPushService());
    $observer->created($order);
    echo "Done! Check email inbox for hello@healingourth.com and customer email.\n";
} else {
    echo "No order found.\n";
}
