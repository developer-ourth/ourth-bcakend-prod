<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the Krayin CRM REST API (krayin/rest-api, routes under /api/v1).
 *
 * Uses a dedicated Krayin user for the integration: Krayin's login endpoint
 * revokes all previous tokens of the user that logs in, so never share these
 * credentials with a person.
 */
class KrayinClient
{
    private const TOKEN_CACHE_KEY = 'krayin:api-token';

    private const LOOKUP_TTL = 3600;

    public function isConfigured(): bool
    {
        return config('services.krayin.url')
            && config('services.krayin.api_email')
            && config('services.krayin.api_password');
    }

    /**
     * Admin URL of a lead, for linking from our dashboards.
     */
    public function leadUrl(int $leadId): string
    {
        return config('services.krayin.url') . "/admin/leads/view/{$leadId}";
    }

    /**
     * Create a lead together with its person. Returns ['lead_id' => int, 'person_id' => int].
     *
     * @param  array{name: string, phone?: ?string, email?: ?string, person_id?: ?int}  $person
     */
    public function createLead(string $title, array $person, string $sourceName, string $description = '', float $value = 0, ?string $stageCode = null): array
    {
        $personData = ! empty($person['person_id'])
            ? ['id' => $person['person_id']]
            : array_filter([
                'name' => $person['name'],
                'contact_numbers' => ! empty($person['phone']) ? [['value' => $person['phone'], 'label' => 'work']] : null,
                'emails' => ! empty($person['email']) ? [['value' => $person['email'], 'label' => 'work']] : null,
            ]);

        $payload = array_filter([
            'title' => $title,
            'description' => $description,
            'lead_value' => $value,
            'lead_source_id' => $this->sourceId($sourceName),
            'lead_type_id' => $this->defaultTypeId(),
            'lead_pipeline_stage_id' => $stageCode ? $this->stageId($stageCode) : null,
            'person' => $personData,
        ], fn ($value) => $value !== null && $value !== '');

        // lead_value is required by Krayin even when it is zero.
        $payload['lead_value'] = $value;

        // Store endpoints wrap the resource twice: {"data": {"data": {...}, "message": "..."}}
        $lead = $this->send('post', 'leads', $payload)->json('data.data');

        if (empty($lead['id'])) {
            throw new RuntimeException('Krayin did not return a lead id.');
        }

        return [
            'lead_id' => (int) $lead['id'],
            'person_id' => (int) ($lead['person']['id'] ?? $lead['person_id'] ?? 0) ?: null,
        ];
    }

    /**
     * Move a lead to the stage with the given code ('new', 'follow-up', 'prospect', 'negotiation', 'won', 'lost').
     */
    public function moveToStage(int $leadId, string $stageCode): void
    {
        $this->send('put', "leads/stage/edit/{$leadId}", [
            'lead_pipeline_stage_id' => $this->stageId($stageCode),
        ]);
    }

    /**
     * Add a note to the lead's activity timeline.
     */
    public function addNote(int $leadId, string $comment): void
    {
        $this->send('post', 'activities', [
            'type' => 'note',
            'title' => 'Note',
            'comment' => $comment,
            'lead_id' => $leadId,
        ]);
    }

    /**
     * Find an existing Krayin person (e.g. one a salesperson created by hand) by
     * phone or email. Both are unique in Krayin, so creating a duplicate would fail.
     */
    public function findPersonId(?string $phone, ?string $email): ?int
    {
        $lastTen = $phone ? substr(preg_replace('/\D/', '', $phone), -10) : null;

        foreach (array_filter(['contact_numbers' => $lastTen, 'emails' => $email]) as $field => $value) {
            $id = $this->send('get', 'contacts/persons/search', [
                'search' => "{$field}:{$value}",
                'searchFields' => "{$field}:like",
                'limit' => 1,
            ])->json('data.0.id');

            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * Find a lead source by name, creating it the first time it is used.
     */
    public function sourceId(string $name): int
    {
        return Cache::remember('krayin:source:' . md5($name), self::LOOKUP_TTL, function () use ($name) {
            $sources = $this->send('get', 'settings/sources', ['pagination' => 0])->json('data') ?? [];

            foreach ($sources as $source) {
                if (strcasecmp($source['name'] ?? '', $name) === 0) {
                    return (int) $source['id'];
                }
            }

            return (int) $this->send('post', 'settings/sources', ['name' => $name])->json('data.data.id');
        });
    }

    private function defaultTypeId(): int
    {
        return Cache::remember('krayin:default-type', self::LOOKUP_TTL, function () {
            $types = $this->send('get', 'settings/types', ['pagination' => 0])->json('data') ?? [];

            if (empty($types)) {
                throw new RuntimeException('Krayin has no lead types configured.');
            }

            return (int) $types[0]['id'];
        });
    }

    private function stageId(string $code): int
    {
        $stages = Cache::remember('krayin:default-pipeline-stages', self::LOOKUP_TTL, function () {
            $pipelines = $this->send('get', 'settings/pipelines', ['pagination' => 0])->json('data') ?? [];
            $pipeline = collect($pipelines)->firstWhere('is_default', true) ?? ($pipelines[0] ?? null);

            if (! $pipeline) {
                throw new RuntimeException('Krayin has no lead pipeline configured.');
            }

            return collect($pipeline['stages'] ?? [])->pluck('id', 'code')->all();
        });

        if (! isset($stages[$code])) {
            throw new RuntimeException("Krayin default pipeline has no stage with code '{$code}'.");
        }

        return (int) $stages[$code];
    }

    private function send(string $method, string $path, array $data = [], bool $allowNotFound = false, bool $retried = false): Response
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Krayin CRM is not configured (KRAYIN_URL / KRAYIN_API_EMAIL / KRAYIN_API_PASSWORD).');
        }

        $response = $this->http()->withToken($this->token())->{$method}($path, $data);

        if ($response->status() === 401 && ! $retried) {
            Cache::forget(self::TOKEN_CACHE_KEY);

            return $this->send($method, $path, $data, $allowNotFound, true);
        }

        if ($allowNotFound && $response->status() === 404) {
            return $response;
        }

        if ($response->failed()) {
            throw new RuntimeException("Krayin API {$method} {$path} failed ({$response->status()}): " . mb_substr($response->body(), 0, 500));
        }

        return $response;
    }

    private function token(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addDays(7), function () {
            $response = $this->http()->post('login', [
                'email' => config('services.krayin.api_email'),
                'password' => config('services.krayin.api_password'),
                'device_name' => 'ourth-backend',
            ]);

            if ($response->failed() || ! $response->json('token')) {
                throw new RuntimeException('Krayin login failed (' . $response->status() . '). Check KRAYIN_API_EMAIL / KRAYIN_API_PASSWORD.');
            }

            return $response->json('token');
        });
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('services.krayin.url') . '/api/v1/')
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->connectTimeout(5);
    }
}
