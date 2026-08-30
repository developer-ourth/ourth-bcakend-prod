<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Coupon;

echo "=== ALL COUPONS IN DATABASE ===\n";
$all = Coupon::all();
foreach ($all as $c) {
    echo "ID: {$c->id} | Code: {$c->code} | Discount: {$c->discount_percentage}% | Active: " . ($c->is_active ? 'YES' : 'NO') . " | Expires: " . ($c->expires_at ?: 'NEVER') . "\n";
}

echo "\n=== ACTIVE COUPONS DISPATCH TEST ===\n";
$req = Illuminate\Http\Request::create('/api/v1/coupons/active', 'GET');
$res = $app->handle($req);
echo "Status: " . $res->getStatusCode() . "\n";
echo "Body: " . $res->getContent() . "\n";
