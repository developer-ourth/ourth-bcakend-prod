<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

$product = Product::find(23);
if ($product) {
    echo "Product ID=23, Name={$product->name}, Unit='{$product->unit}'\n";
} else {
    echo "Product 23 not found!\n";
}
