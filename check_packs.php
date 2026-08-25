<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

$product = Product::with('packs')->find(23);
echo "Product ID: 23, Name: " . $product->name . "\n";
echo "Total Packs loaded: " . $product->packs->count() . "\n";
foreach ($product->packs as $pack) {
    echo "Pack ID: {$pack->id}, Name: '{$pack->name}', Is Active: " . ($pack->is_active ? 'YES' : 'NO') . ", Deleted At: " . ($pack->deleted_at ?? 'NULL') . "\n";
}
