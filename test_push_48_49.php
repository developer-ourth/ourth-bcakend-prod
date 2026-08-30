<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;
use App\Services\ShadowfaxService;

$sfx = new ShadowfaxService();

foreach ([48, 49] as $id) {
    echo "=== Pushing Order #{$id} ===\n";
    $order = Order::find($id);
    if (!$order) {
        echo "Order #{$id} not found.\n";
        continue;
    }
    
    $res = $sfx->createOrder($order);
    if ($res && isset($res['awb_number'])) {
        $order->update([
            'awb_number' => $res['awb_number'],
            'tracking_url' => $res['tracking_url'] ?? null
        ]);
        echo "SUCCESS! AWB: {$res['awb_number']}\n\n";
    } else {
        echo "FAILED for Order #{$id}! Check output above.\n\n";
    }
}
