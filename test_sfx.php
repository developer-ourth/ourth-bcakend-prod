<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing Shadowfax connection...\n";
$baseUrl = config('services.shadowfax.base_url', 'https://api.shadowfax.in');
$token = config('services.shadowfax.api_token') ?: env('SHADOWFAX_API_TOKEN');

if (!$token) {
    echo "Error: Token not found in config/env.\n";
    exit(1);
}

echo "Using Base URL: {$baseUrl}\n";
echo "Token: " . substr($token, 0, 5) . "***\n";

$payload = [
    'order_type' => 'warehouse',
    'order_details' => [
        'client_order_id' => 'TEST-' . rand(1000, 9999),
        'actual_weight' => 1.0,
        'volumetric_weight' => 1.0,
        'product_value' => 500,
        'payment_mode' => 'Prepaid',
        'cod_amount' => 0,
        'total_amount' => 500,
        'order_service' => 'forward'
    ],
    'customer_details' => [
        'name' => 'Test Customer',
        'contact' => '9999999999',
        'address_line_1' => 'Test Address',
        'city' => 'Mumbai',
        'state' => 'MH',
        'pincode' => '400001',
        'location_type' => 'residential'
    ],
    'pickup_details' => [
        'unique_code' => 'WH01',
        'name' => 'Healing Ourth Warehouse',
        'contact' => '1800OURTHCARE',
        'address_line_1' => 'Main Warehouse',
        'city' => 'Mumbai',
        'state' => 'MH',
        'pincode' => '400001',
    ],
    'rto_details' => [
        'unique_code' => 'WH01',
        'name' => 'Healing Ourth Returns',
        'contact' => '1800OURTHCARE',
        'address_line_1' => 'Returns Processing',
        'city' => 'Mumbai',
        'state' => 'MH',
        'pincode' => '400001',
    ],
    'product_details' => [
        [
            'name' => 'Healing Ourth Product',
            'quantity' => 1,
            'price' => 500,
            'seller_details' => [
                'name' => 'Healing Ourth India Pvt Ltd'
            ]
        ]
    ]
];

$response = \Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => "Token {$token}",
    'Accept' => 'application/json'
])->post("{$baseUrl}/api/v3/clients/orders/", $payload);

echo "Status Code: " . $response->status() . "\n";
echo "Response: " . $response->body() . "\n";

if ($response->status() === 401 || $response->status() === 403) {
    echo "\n[FAILED] Connection failed! Unauthorized - The token is invalid.\n";
} else {
    echo "\n[SUCCESS] Connection successful! The API responded (Auth is valid).\n";
}
