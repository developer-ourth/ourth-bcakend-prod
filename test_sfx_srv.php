<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$services = ['sds', 'sds-forward', 'forward', 'surface', 'air', 'express'];

foreach ($services as $srv) {
    $response = Illuminate\Support\Facades\Http::withHeaders([
        'Authorization' => 'Token b7a02c8e44650f3778fee8cbb166db9d112b468c',
        'Content-Type' => 'application/json'
    ])->post('https://api.shadowfax.in/api/v2/clients/orders/', [
        'order_details' => [
            'client_order_id' => 'ORD-12345678' . rand(1, 1000),
            'actual_weight' => 1.0, 
            'volumetric_weight' => 1.0,
            'product_value' => 100,
            'payment_mode' => 'COD',
            'cod_amount' => 100,
            'total_amount' => 100,
            'order_service' => $srv
        ],
        'customer_details' => [
            'name' => 'John Doe',
            'contact' => '9999999999',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'address_line_1' => 'Test Address',
            'pincode' => '110001'
        ],
        'pickup_details' => [
            'name' => 'Store Name',
            'contact' => '8888888888',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'address_line_1' => 'Test Pickup Address',
            'pincode' => '110002'
        ],
        'products' => [
            [
                'sku' => 'TEST-SKU',
                'product_name' => 'Test Item',
                'price' => 100,
                'quantity' => 1
            ]
        ]
    ]);
    
    echo "Service $srv: " . $response->body() . "\n";
}
