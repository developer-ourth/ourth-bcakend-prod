<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    /**
     * Send a push notification to all registered device tokens for a user.
     *
     * @param  array<string, mixed>  $data  Extra payload delivered to the app
     */
    public function notifyUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::where('user_id', $userId)->pluck('token')->all();

        if (empty($tokens)) {
            return;
        }

        $messages = array_map(fn (string $token): array => [
            'to'    => $token,
            'title' => $title,
            'body'  => $body,
            'data'  => $data,
            'sound' => 'default',
        ], $tokens);

        try {
            $response = Http::withHeaders(['Accept-Encoding' => 'gzip, deflate'])
                ->post(self::EXPO_PUSH_URL, $messages);

            if ($response->failed()) {
                Log::warning('Expo push notification failed', [
                    'user_id' => $userId,
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Expo push notification exception', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
