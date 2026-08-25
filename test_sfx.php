<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$order = App\Models\Order::find(41);
echo "Order: {$order->order_number}\n";
echo "Payment: {$order->payment?->payment_gateway}\n";
echo "Address: {$order->delivery_address_line1}\n";
echo "City: {$order->delivery_city}\n";
echo "Pincode: {$order->delivery_postal_code}\n";
echo "Phone: {$order->delivery_phone}\n";
echo "Name: {$order->delivery_name}\n\n";

$sfx = new App\Services\ShadowfaxService();
$result = $sfx->createOrder($order);
echo "Result:\n";
var_dump($result);
