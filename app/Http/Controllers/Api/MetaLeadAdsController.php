<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\VerifiesMetaWebhook;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\LeadCaptureService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaLeadAdsController extends Controller
{
    use VerifiesMetaWebhook;

    /**
     * Handle incoming Meta Lead Form submission (POST). Signature is checked by the meta.signature middleware.
     */
    public function handleWebhook(Request $request, LeadCaptureService $leads)
    {
        $accessToken = config('services.meta.leads_access_token')
            ?: (AppSetting::where('key', 'meta_capi_token')->value('value') ?: env('META_CAPI_TOKEN'));

        if (!$accessToken) {
            Log::error('Meta Lead Ads webhook: no access token configured (META_LEADS_ACCESS_TOKEN).');
            return response()->json(['status' => 'error', 'message' => 'Not configured'], 503);
        }

        $wa = new WhatsAppService();

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $leadgenId = $value['leadgen_id'] ?? null;

                // Meta retries deliveries, so skip leads we already handled.
                if (!$leadgenId || Cache::has("meta-lead:{$leadgenId}")) {
                    continue;
                }

                // Fetch the submitted field values; Meta's webhook only carries the lead id.
                $res = Http::timeout(15)->get("https://graph.facebook.com/v19.0/{$leadgenId}", [
                    'access_token' => $accessToken,
                ]);

                if (!$res->successful()) {
                    Log::error("Meta Lead Ads: could not fetch lead {$leadgenId}: " . $res->body());
                    // Let Meta retry later instead of losing the lead.
                    return response()->json(['status' => 'error'], 502);
                }

                $leadData = $res->json();
                $fields = $this->parseFields($leadData['field_data'] ?? []);

                $result = $leads->capture(
                    phone: $fields['phone'],
                    name: $fields['name'],
                    sourceType: 'meta_lead_ad',
                    segment: 'B2B_Distributor',
                    email: $fields['email'],
                    touchpoint: [
                        'ad_id' => $value['ad_id'] ?? null,
                        'adset_id' => $value['adgroup_id'] ?? null,
                        'campaign_id' => $value['campaign_id'] ?? null,
                        'form_id' => $value['form_id'] ?? null,
                        'raw_payload' => $leadData,
                    ],
                    note: $fields['summary'],
                );

                Cache::put("meta-lead:{$leadgenId}", true, now()->addDays(7));

                if (!$result) {
                    Log::warning("Meta Lead Ads: lead {$leadgenId} has no usable phone number.");
                    continue;
                }

                // A form lead has never messaged us, so WhatsApp only delivers an approved template.
                $template = config('services.meta.lead_welcome_template');
                if ($template) {
                    $wa->sendTemplate($result['user']->phone, $template, config('services.meta.template_language'), [$result['user']->name]);
                } else {
                    Log::info('Meta Lead Ads: META_LEAD_WELCOME_TEMPLATE not set, skipping WhatsApp welcome message.');
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * @return array{phone: ?string, name: ?string, email: ?string, summary: string}
     */
    private function parseFields(array $fieldData): array
    {
        $parsed = ['phone' => null, 'name' => null, 'email' => null, 'summary' => ''];
        $firstName = $lastName = null;
        $lines = [];

        foreach ($fieldData as $field) {
            $key = strtolower($field['name'] ?? '');
            $val = trim((string) ($field['values'][0] ?? ''));

            if ($val === '') {
                continue;
            }

            $lines[] = str_replace('_', ' ', ucfirst($key)) . ': ' . $val;

            if (str_contains($key, 'phone')) $parsed['phone'] = $val;
            elseif ($key === 'full_name') $parsed['name'] = $val;
            elseif ($key === 'first_name') $firstName = $val;
            elseif ($key === 'last_name') $lastName = $val;
            elseif (str_contains($key, 'email')) $parsed['email'] = $val;
        }

        $parsed['name'] ??= trim("{$firstName} {$lastName}") ?: null;
        $parsed['summary'] = "Meta lead form answers:\n" . implode("\n", $lines);

        return $parsed;
    }
}
