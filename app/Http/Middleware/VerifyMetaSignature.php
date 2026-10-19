<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects Meta webhook POSTs (Lead Ads, WhatsApp Cloud API) whose
 * X-Hub-Signature-256 does not match HMAC-SHA256(raw body, META_APP_SECRET).
 * GET verification handshakes pass through.
 */
class VerifyMetaSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        $secret = config('services.meta.app_secret');

        if (! $secret) {
            Log::error('Meta webhook rejected: META_APP_SECRET is not configured.');

            return response()->json(['error' => 'Webhook not configured'], 503);
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
        $given = (string) $request->header('X-Hub-Signature-256');

        if (! hash_equals($expected, $given)) {
            Log::warning('Meta webhook rejected: invalid signature.', ['path' => $request->path()]);

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        return $next($request);
    }
}
