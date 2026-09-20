<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\AttributionTouchpoint;

class WhatsappCtwaWebhookController extends Controller
{
    /**
     * Handle incoming webhooks from WhatsApp CTWA (Click to WhatsApp Ads) or Chatwoot
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->all();
        Log::info('WhatsApp CTWA Referral Webhook Received:', $payload);

        try {
            $message = $payload['message'] ?? $payload;
            $referral = $payload['referral'] ?? ($message['referral'] ?? ($message['attachments'][0]['referral'] ?? null));

            $phone = $payload['sender']['phone_number'] ?? ($payload['contact']['phone_number'] ?? ($payload['phone'] ?? null));
            $name = $payload['sender']['name'] ?? ($payload['contact']['name'] ?? ($payload['name'] ?? 'WhatsApp User'));

            if (!$phone && !$referral) {
                return response()->json(['message' => 'No phone or referral data found'], 200);
            }

            // Find or create user
            $user = null;
            if ($phone) {
                $cleanPhone = preg_replace('/\D/', '', $phone);
                $user = User::firstOrCreate(
                    ['phone_number' => $cleanPhone],
                    ['name' => $name, 'user_type' => 'B2C']
                );
            }

            // Record touchpoint if referral ad information is present
            if ($referral || $user) {
                $adId = $referral['source_id'] ?? ($referral['ad_id'] ?? null);
                $campaignId = $referral['campaign_id'] ?? null;
                $headline = $referral['headline'] ?? ($referral['title'] ?? 'Click to WhatsApp Ad');

                $touchpoint = AttributionTouchpoint::create([
                    'user_id' => $user?->id,
                    'source_type' => 'whatsapp_ctwa',
                    'ad_id' => $adId,
                    'campaign_id' => $campaignId,
                    'campaign_name' => $headline,
                    'raw_payload' => $payload,
                ]);

                Log::info("Recorded CTWA Touchpoint ID {$touchpoint->id} for User ID {$user?->id}");
            }

            return response()->json(['status' => 'success', 'message' => 'Webhook processed'], 200);
        } catch (\Exception $e) {
            Log::error('WhatsApp CTWA Webhook Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
