<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;

echo "--- 1. Testing /api/v1/auth/otp/send-phone ---\n";
$request = Request::create('/api/v1/auth/otp/send-phone', 'POST', [], [], [], [], json_encode(['phone' => '8851475721']));
$request->headers->set('Content-Type', 'application/json');
$request->headers->set('Accept', 'application/json');
$response = $app->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getContent() . "\n\n";

$cachedOtp = Illuminate\Support\Facades\Cache::get('otp_phone_8851475721');
echo "Cached Phone OTP for 8851475721: " . ($cachedOtp ?: 'None') . "\n\n";

echo "--- 2. Testing /api/v1/auth/otp/verify with correct Phone OTP ---\n";
$request2 = Request::create('/api/v1/auth/otp/verify', 'POST', [], [], [], [], json_encode([
    'identifier' => '8851475721',
    'otp' => $cachedOtp,
    'type' => 'phone'
]));
$request2->headers->set('Content-Type', 'application/json');
$request2->headers->set('Accept', 'application/json');
$response2 = $app->handle($request2);
echo "Status: " . $response2->getStatusCode() . "\n";
echo "Body: " . $response2->getContent() . "\n";
