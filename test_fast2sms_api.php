<?php
$apiKey = 'CDEt7Hr8cXAVYKxlTmivJ5PqUSIWoLFGQzs1fgNh20OZpw4deaSV3Atrcg8FuG4nqxDRTCEkd0eIJ1MB';
$phone = '8851475721';
$otp = '123456';

echo "--- Testing Fast2SMS POST bulkV2 ---\n";
$ch = curl_init('https://www.fast2sms.com/dev/bulkV2');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'variables_values' => $otp,
    'route' => 'otp',
    'numbers' => $phone
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "authorization: $apiKey",
    "Content-Type: application/json"
]);
$res = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "Status: $status\n";
echo "Response: $res\n\n";

echo "--- Testing Fast2SMS GET bulkV2 ---\n";
$url = "https://www.fast2sms.com/dev/bulkV2?authorization=$apiKey&route=otp&variables_values=$otp&flash=0&numbers=$phone";
$res2 = file_get_contents($url);
echo "GET Response: $res2\n";
