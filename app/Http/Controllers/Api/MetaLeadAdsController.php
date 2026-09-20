<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\AttributionTouchpoint;
use App\Services\WhatsAppService;

class MetaLeadAdsController extends Controller
{
    /**
     * Webhook verification for Meta Lead Ads (GET)
     */
    public function verifyWebhook(Request $request)
    {
        $verifyToken = config('services.meta.verify_token', 'OURTH_LEAD_VERIFY_TOKEN_2026');
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('Meta Lead Ads Webhook Verified Successfully');
            return response($challenge, 200);
        }

        return response()->json(['error' => 'Forbidden'], 403);
    }

    /**
     * Handle incoming Meta Lead Form submission (POST)
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->all();
        Log::info('Meta Lead Ads Form Submission Received:', $payload);

        try {
            $entries = $payload['entry'] ?? [];
            $wa = new WhatsAppService();

            foreach ($entries as $entry) {
                $changes = $entry['changes'] ?? [];
                foreach ($changes as $change) {
                    $value = $change['value'] ?? [];
                    $leadgenId = $value['leadgen_id'] ?? null;
                    $formId = $value['form_id'] ?? null;
                    $adId = $value['ad_id'] ?? null;

                    if ($leadgenId) {
                        // Fetch full lead field values from Meta Graph API
                        $accessToken = config('services.meta.capi_token') ?: env('META_CAPI_TOKEN');
                        if ($accessToken) {
                            $res = Http::get("https://graph.facebook.com/v19.0/{$leadgenId}?access_token={$accessToken}");
                            if ($res->successful()) {
                                $leadData = $res.json();
                                $fieldData = $leadData['field_data'] ?? [];

                                $phone = null;
                                $name = 'Valued Customer';
                                $email = null;

                                foreach ($fieldData as $field) {
                                    $nameKey = strtolower($field['name'] ?? '');
                                    $values = $field['values'] ?? [];
                                    $val = $values[0] ?? '';

                                    if (str_contains($nameKey, 'phone')) $phone = $val;
                                    elseif (str_contains($nameKey, 'full_name') || str_contains($nameKey, 'first_name')) $name = $val;
                                    elseif (str_contains($nameKey, 'email')) $email = $val;
                                }

                                if ($phone) {
                                    $cleanPhone = preg_replace('/\D/', '', $phone);
                                    $user = User::firstOrCreate(
                                        ['phone' => $cleanPhone],
                                        ['name' => $name, 'email' => $email, 'user_type' => 'B2B_Distributor']
                                    );

                                    AttributionTouchpoint::create([
                                        'user_id' => $user->id,
                                        'source_type' => 'meta_lead_ad',
                                        'ad_id' => $adId,
                                        'form_id' => $formId,
                                        'raw_payload' => $leadData,
                                    ]);

                                    // Send instant WhatsApp welcome greeting + B2B wholesale catalog
                                    $waGreeting = "🌿 *Welcome to OURTH! Thank you for inquiring.*\n\n"
                                                . "Hi {$name},\n"
                                                . "We received your inquiry for 100% natural, eco-friendly Areca Leaf Tableware.\n\n"
                                                . "📖 *Download B2B Wholesale Catalog:* https://www.healingourth.com/catalog\n"
                                                . "🛒 *Shop Online:* https://www.healingourth.com/products\n\n"
                                                . "Our sales executive will connect with you shortly!";
                                    
                                    $wa->sendMessage($cleanPhone, $waGreeting);
                                }
                            }
                        }
                    }
                }
            }

            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            Log::error('Meta Lead Ads Webhook Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
