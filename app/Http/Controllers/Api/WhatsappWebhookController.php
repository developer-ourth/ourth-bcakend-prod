<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\VerifiesMetaWebhook;
use App\Http\Controllers\Controller;
use App\Services\LeadCaptureService;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Receives Meta's WhatsApp Cloud API webhook (Meta allows one callback URL per
 * app), records Click-to-WhatsApp ad attribution and new-contact leads, then
 * forwards the untouched payload to the Chatwoot inbox. Chatwoot verifies the
 * same X-Hub-Signature-256, so the raw body and header are passed through as-is.
 */
class WhatsappWebhookController extends Controller
{
    use VerifiesMetaWebhook;

    public function handleWebhook(Request $request, LeadCaptureService $leads)
    {
        try {
            $this->recordLeads($request->input('entry', []), $leads);
        } catch (\Throwable $e) {
            // Attribution must never stop messages reaching the inbox.
            Log::error('WhatsApp webhook attribution error: ' . $e->getMessage());
        }

        return $this->forwardToChatwoot($request);
    }

    private function recordLeads(array $entries, LeadCaptureService $leads): void
    {
        foreach ($entries as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? [];
                $names = collect($value['contacts'] ?? [])->mapWithKeys(
                    fn ($contact) => [($contact['wa_id'] ?? '') => $contact['profile']['name'] ?? null]
                );

                foreach ($value['messages'] ?? [] as $message) {
                    $messageId = $message['id'] ?? null;

                    // Meta retries until it gets a 200, so each message is processed once.
                    if (!$messageId || !Cache::add("wa-msg:{$messageId}", true, now()->addDays(3))) {
                        continue;
                    }

                    $phone = $message['from'] ?? null;
                    $name = $names[$phone] ?? null;
                    $referral = $message['referral'] ?? null;

                    if ($referral && ($referral['source_type'] ?? null) === 'ad') {
                        $leads->capture(
                            phone: $phone,
                            name: $name,
                            sourceType: 'whatsapp_ctwa',
                            touchpoint: [
                                'ad_id' => $referral['source_id'] ?? null,
                                'campaign_name' => $referral['headline'] ?? 'Click to WhatsApp Ad',
                                'fbclid' => $referral['ctwa_clid'] ?? null,
                                'raw_payload' => ['referral' => $referral, 'message_id' => $messageId],
                            ],
                            note: 'Started a WhatsApp chat from ad: ' . ($referral['headline'] ?? $referral['source_url'] ?? 'unknown ad'),
                        );
                    } elseif (!PhoneNumber::findUser($phone)) {
                        // First message from an unknown number is a new lead.
                        $leads->capture(
                            phone: $phone,
                            name: $name,
                            sourceType: 'whatsapp_inbound',
                            touchpoint: ['raw_payload' => ['message_id' => $messageId]],
                            note: 'First WhatsApp message: ' . mb_substr($message['text']['body'] ?? "[{$message['type']}]", 0, 500),
                        );
                    }
                }
            }
        }
    }

    private function forwardToChatwoot(Request $request)
    {
        $url = config('services.chatwoot.whatsapp_webhook_url');

        if (!$url) {
            return response()->json(['status' => 'success']);
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(array_filter([
                    'X-Hub-Signature-256' => $request->header('X-Hub-Signature-256'),
                ]))
                ->withBody($request->getContent(), 'application/json')
                ->post($url);

            if ($response->successful()) {
                return response()->json(['status' => 'success']);
            }

            Log::error("Chatwoot rejected forwarded WhatsApp webhook ({$response->status()}): " . mb_substr($response->body(), 0, 500));
        } catch (\Throwable $e) {
            Log::error('Could not forward WhatsApp webhook to Chatwoot: ' . $e->getMessage());
        }

        // Non-2xx makes Meta retry, so the message is not lost while Chatwoot is down.
        return response()->json(['status' => 'error'], 502);
    }
}
