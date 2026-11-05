<?php

namespace App\Services\Zoho;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Zoho Inventory API v1 client (India DC by default). Uses a Self Client
 * refresh token; access tokens (1 hour) are cached and refreshed on demand.
 *
 * Every response carries {"code": 0, ...} on success; anything else throws ZohoApiException.
 */
class ZohoInventoryClient
{
    private const TOKEN_CACHE_KEY = 'zoho:access-token';

    public function isConfigured(): bool
    {
        return config('services.zoho.client_id')
            && config('services.zoho.client_secret')
            && config('services.zoho.refresh_token')
            && config('services.zoho.organization_id');
    }

    public function get(string $path, array $query = []): array
    {
        return $this->send('get', $path, $query);
    }

    public function post(string $path, array $body = [], array $query = []): array
    {
        return $this->send('post', $path, $body, $query);
    }

    /**
     * Fetch every page of a list endpoint ({"<key>": [...], "page_context": {"has_more_page": bool}}).
     */
    public function all(string $path, string $key, array $query = []): array
    {
        $records = [];
        $page = 1;

        do {
            $response = $this->get($path, array_merge($query, ['page' => $page, 'per_page' => 200]));
            array_push($records, ...($response[$key] ?? []));
            $page++;
        } while (($response['page_context']['has_more_page'] ?? false) && $page <= 100);

        return $records;
    }

    /**
     * Exchange a one-time grant code (API Console → Self Client → Generate Code) for a permanent refresh token.
     */
    public function exchangeGrantCode(string $code): array
    {
        $response = Http::asForm()->timeout(20)->post(config('services.zoho.accounts_url') . '/oauth/v2/token', [
            'client_id' => config('services.zoho.client_id'),
            'client_secret' => config('services.zoho.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        if ($response->failed() || ! $response->json('refresh_token')) {
            throw new ZohoApiException('Zoho rejected the grant code: ' . ($response->json('error') ?? $response->body()) . '. Codes expire after a few minutes; generate a new one.');
        }

        return $response->json();
    }

    private function send(string $method, string $path, array $data = [], array $query = [], bool $retried = false): array
    {
        if (! $this->isConfigured()) {
            throw new ZohoApiException('Zoho Inventory is not configured (ZOHO_CLIENT_ID / ZOHO_CLIENT_SECRET / ZOHO_REFRESH_TOKEN / ZOHO_ORGANIZATION_ID).');
        }

        $query['organization_id'] = config('services.zoho.organization_id');
        $url = '/' . ltrim($path, '/');

        $request = $this->http()->withHeaders(['Authorization' => 'Zoho-oauthtoken ' . $this->accessToken()]);

        /** @var Response $response */
        $response = $method === 'get'
            ? $request->get($url, array_merge($data, $query))
            : $request->{$method}($url . '?' . http_build_query($query), $data);

        if ($response->status() === 401 && ! $retried) {
            Cache::forget(self::TOKEN_CACHE_KEY);

            return $this->send($method, $path, $data, $query, true);
        }

        $body = $response->json() ?? [];

        if ($response->failed() || ($body['code'] ?? 0) !== 0) {
            throw new ZohoApiException(
                "Zoho {$method} {$path} failed ({$response->status()}): " . ($body['message'] ?? mb_substr($response->body(), 0, 300)),
                (int) ($body['code'] ?? 0),
                $response->status(),
            );
        }

        return $body;
    }

    private function accessToken(): string
    {
        if ($token = Cache::get(self::TOKEN_CACHE_KEY)) {
            return $token;
        }

        // One refresh at a time: Zoho limits how often tokens can be refreshed.
        return Cache::lock('zoho:token-refresh', 30)->block(20, function () {
            if ($token = Cache::get(self::TOKEN_CACHE_KEY)) {
                return $token;
            }

            $response = Http::asForm()->timeout(20)->post(config('services.zoho.accounts_url') . '/oauth/v2/token', [
                'client_id' => config('services.zoho.client_id'),
                'client_secret' => config('services.zoho.client_secret'),
                'refresh_token' => config('services.zoho.refresh_token'),
                'grant_type' => 'refresh_token',
            ]);

            $token = $response->json('access_token');

            if ($response->failed() || ! $token) {
                throw new ZohoApiException('Zoho token refresh failed: ' . ($response->json('error') ?? $response->body()) . '. Check ZOHO_CLIENT_ID / ZOHO_CLIENT_SECRET / ZOHO_REFRESH_TOKEN.');
            }

            $ttl = max(60, (int) $response->json('expires_in', 3600) - 300);
            Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

            return $token;
        });
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('services.zoho.api_url'))
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->connectTimeout(10);
    }
}
