<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

/**
 * Meta's GET handshake when a webhook URL is registered in the App Dashboard.
 */
trait VerifiesMetaWebhook
{
    public function verifyWebhook(Request $request)
    {
        $verifyToken = config('services.meta.verify_token');
        $token = (string) $request->query('hub_verify_token');

        if ($verifyToken && $request->query('hub_mode') === 'subscribe' && hash_equals($verifyToken, $token)) {
            return response($request->query('hub_challenge'), 200);
        }

        return response()->json(['error' => 'Forbidden'], 403);
    }
}
