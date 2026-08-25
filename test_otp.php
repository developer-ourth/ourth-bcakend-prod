<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;

echo "--- 1. Testing /api/v1/auth/otp/send-email ---\n";
$request = Request::create('/api/v1/auth/otp/send-email', 'POST', [], [], [], [], json_encode(['email' => 'sahil.bhargava52@gmail.com']));
$request->headers->set('Content-Type', 'application/json');
$request->headers->set('Accept', 'application/json');
$response = $app->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getContent() . "\n\n";

echo "--- 2. Testing /api/v1/auth/otp/send (Mobile App Alias) ---\n";
$request2 = Request::create('/api/v1/auth/otp/send', 'POST', [], [], [], [], json_encode(['email' => 'sahil.bhargava52@gmail.com']));
$request2->headers->set('Content-Type', 'application/json');
$request2->headers->set('Accept', 'application/json');
$response2 = $app->handle($request2);
echo "Status: " . $response2->getStatusCode() . "\n";
echo "Body: " . $response2->getContent() . "\n\n";

$cachedOtp = Illuminate\Support\Facades\Cache::get('otp_sahil.bhargava52@gmail.com');
echo "Cached OTP generated for sahil.bhargava52@gmail.com: " . ($cachedOtp ?: 'None') . "\n\n";

echo "--- 3. Testing /api/v1/auth/otp/verify with correct OTP ---\n";
$request3 = Request::create('/api/v1/auth/otp/verify', 'POST', [], [], [], [], json_encode([
    'identifier' => 'sahil.bhargava52@gmail.com',
    'otp' => $cachedOtp,
    'type' => 'email'
]));
$request3->headers->set('Content-Type', 'application/json');
$request3->headers->set('Accept', 'application/json');
$response3 = $app->handle($request3);
echo "Status: " . $response3->getStatusCode() . "\n";
echo "Body: " . $response3->getContent() . "\n";
