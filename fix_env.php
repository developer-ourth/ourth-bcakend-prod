<?php
$envPath = __DIR__ . '/.env';
$content = file_get_contents($envPath);
$content = preg_replace('/[^\x20-\x7E\r\n]/', '', $content);
$content = str_replace('S H A D O W F A X _ W E B H O O K _ T O K E N = O u r t h W e b h o o k S e c r e t 1 2 3 !', 'SHADOWFAX_WEBHOOK_TOKEN=OurthWebhookSecret123!', $content);
file_put_contents($envPath, $content);
echo "Fixed .env!\n";
