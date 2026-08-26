<?php
require __DIR__.'/vendor/autoload.php';

$token = 'b7a02c8e44650f3778fee8cbb166db9d112b468c';
$pincode = '110078';

// Test various Shadowfax endpoint formats
$endpoints = [
    'https://dale.shadowfax.in/api/v3/clients/orders/serviceability/?pickup_pincode=400001&delivery_pincode='.$pincode,
    'https://dale.shadowfax.in/api/v2/clients/serviceability/?pickup_pincode=400001&delivery_pincode='.$pincode,
    'https://dale.shadowfax.in/api/v3/clients/serviceability/?pincode='.$pincode,
    'https://dale.shadowfax.in/api/v2/clients/request-serviceability/?pincode='.$pincode,
];

foreach ($endpoints as $url) {
    echo "Testing: $url\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Token $token",
        "Content-Type: application/json",
        "Accept: application/json"
    ]);
    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "Status: $status\n";
    echo "Response: " . substr($res, 0, 200) . "\n\n";
}
