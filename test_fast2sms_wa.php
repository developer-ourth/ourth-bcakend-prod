<?php
$apiKey = 'CDEt7Hr8cXAVYKxlTmivJ5PqUSIWoLFGQzs1fgNh20OZpw4deaSV3Atrcg8FuG4nqxDRTCEkd0eIJ1MB';
$phone = '8851475721';
$otp = '123456';

$routes = [
    ['route' => 'wa', 'variables_values' => $otp],
    ['route' => 'whatsapp', 'variables_values' => $otp],
];

foreach ($routes as $r) {
    echo "Testing route: " . json_encode($r) . "\n";
    $payload = array_merge(['numbers' => $phone], $r);
    $ch = curl_init('https://www.fast2sms.com/dev/bulkV2');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "authorization: $apiKey",
        "Content-Type: application/json"
    ]);
    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "Status: $status\n";
    echo "Response: $res\n\n";
}
