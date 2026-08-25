<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
$p = Product::find(23);
echo "ID: 23, Name: '{$p->name}', Unit: '{$p->unit}'\n";
