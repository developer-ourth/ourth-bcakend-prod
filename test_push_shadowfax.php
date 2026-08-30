<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ShadowfaxService;

echo "=== SHADOWFAX LIVE ORDER PUSH TEST ===\n";

// Find latest order or create a dummy order for testing
$order = Order::orderBy('id', 'desc')->first();

if (!$order) {
    echo "No order found. Creating a test order...\n";
    $order = Order::create([
        'order_number' => 'ORD-TEST-' . time(),
        'user_id' => 1,
        'vendor_id' => 1,
        'order_type' => 'b2c',
        'order_status' => 'confirmed',
        'payment_status' => 'paid',
        'subtotal' => 500,
        'discount_amount' => 0,
        'delivery_charge' => 50,
        'tax_amount' => 0,
        'total_amount' => 550,
        'delivery_address_line1' => 'Plot 12, MG Road, Sector 14',
        'delivery_city' => 'Delhi',
        'delivery_state' => 'Delhi',
        'delivery_postal_code' => '110078',
        'delivery_phone' => '8851475721',
        'customer_notes' => 'Test order for Shadowfax integration',
    ]);
}

echo "Testing Order ID: #{$order->id}\n";
echo "Order Number: {$order->order_number}\n";
echo "Pincode: {$order->delivery_postal_code}\n";
echo "Total Amount: ₹{$order->total_amount}\n\n";

$sfx = new ShadowfaxService();
$result = $sfx->createOrder($order);

echo "=== RESULT FROM SHADOWFAX ===\n";
if ($result && isset($result['awb_number'])) {
    echo "SUCCESS! Order pushed to Shadowfax successfully!\n";
    echo "AWB Number: " . $result['awb_number'] . "\n";
    echo "Tracking URL: " . ($result['tracking_url'] ?? 'N/A') . "\n";
} else {
    echo "FAILED! Check laravel.log or response details above.\n";
    var_dump($result);
}
