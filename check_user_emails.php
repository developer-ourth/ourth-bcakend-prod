<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;
use App\Models\User;

$orders = Order::orderBy('id', 'desc')->take(5)->get();
foreach ($orders as $o) {
    $u = $o->user;
    echo "Order #{$o->id} ({$o->order_number}): User ID={$u?->id}, Name={$u?->name}, Email={$u?->email}, Phone={$u?->phone}\n";
}
