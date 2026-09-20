<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\MetaCapiLog;
use App\Models\AppSetting;

class MetaCapiService
{
    protected ?string $pixelId;
    protected ?string $accessToken;

    public function __construct()
    {
        $this->pixelId = config('services.meta.pixel_id') 
            ?: (AppSetting::where('key', 'meta_pixel_id')->value('value') ?: env('META_PIXEL_ID'));
            
        $this->accessToken = config('services.meta.capi_token') 
            ?: (AppSetting::where('key', 'meta_capi_token')->value('value') ?: env('META_CAPI_TOKEN'));
    }

    /**
     * SHA-256 helper for Meta CAPI normalized match parameters
     */
    protected function hash(?string $value): ?string
    {
        if (!$value) return null;
        $normalized = strtolower(trim($value));
        return hash('sha256', $normalized);
    }

    /**
     * Normalizes phone number to E.164 format without leading '+'
     */
    protected function formatPhone(?string $phone): ?string
    {
        if (!$phone) return null;
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 10) {
            return '91' . $digits; // Default India country code
        }
        return $digits;
    }

    /**
     * Send a Purchase event to Meta Conversions API (CAPI)
     */
    public function sendPurchaseEvent(Order $order): bool
    {
        if (!$this->pixelId || !$this->accessToken) {
            Log::warning("Meta CAPI skip: Missing META_PIXEL_ID or META_CAPI_TOKEN for order #{$order->id}");
            return false;
        }

        try {
            $eventId = 'PURCHASE-' . $order->order_number;

            // Extract customer info
            $phone = $this->formatPhone($order->delivery_phone ?: ($order->user?->phone_number ?: ''));
            $email = $order->delivery_email ?: ($order->user?->email ?: '');
            $fullName = trim($order->delivery_name ?: ($order->user?->name ?: 'Customer'));
            $nameParts = explode(' ', $fullName, 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';

            $userData = array_filter([
                'ph' => $phone ? [$this->hash($phone)] : null,
                'em' => $email ? [$this->hash($email)] : null,
                'fn' => $firstName ? [$this->hash($firstName)] : null,
                'ln' => $lastName ? [$this->hash($lastName)] : null,
                'ct' => $order->delivery_city ? [$this->hash($order->delivery_city)] : null,
                'st' => $order->delivery_state ? [$this->hash($order->delivery_state)] : null,
                'zp' => $order->delivery_postal_code ? [$this->hash($order->delivery_postal_code)] : null,
                'country' => [$this->hash('in')],
                'external_id' => $order->user_id ? [$this->hash((string) $order->user_id)] : null,
            ]);

            // Build dynamic items list
            $items = $order->relationLoaded('items') ? $order->items : $order->items()->with('product')->get();
            $contents = [];
            if ($items && $items->count() > 0) {
                foreach ($items as $item) {
                    $contents[] = [
                        'id' => 'SKU-' . ($item->product_id ?? $item->id),
                        'quantity' => (int) ($item->quantity ?? 1),
                        'item_price' => (float) ($item->unit_price ?: ($item->total_price / max(1, $item->quantity))),
                    ];
                }
            }

            $payload = [
                'data' => [
                    [
                        'event_name' => 'Purchase',
                        'event_time' => time(),
                        'event_id' => $eventId,
                        'event_source_url' => 'https://www.healingourth.com/checkout',
                        'action_source' => 'website',
                        'user_data' => $userData,
                        'custom_data' => [
                            'currency' => 'INR',
                            'value' => (float) $order->total_amount,
                            'order_id' => $order->order_number,
                            'content_type' => 'product',
                            'contents' => $contents,
                        ],
                    ],
                ],
            ];

            $endpoint = "https://graph.facebook.com/v19.0/{$this->pixelId}/events?access_token={$this->accessToken}";

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post($endpoint, $payload);

            $status = $response->successful() ? 'SUCCESS' : 'FAILED';

            // Log event execution
            MetaCapiLog::create([
                'order_id' => $order->id,
                'event_name' => 'Purchase',
                'event_id' => $eventId,
                'status' => $status,
                'response_body' => $response->json() ?? ['raw' => $response->body()],
            ]);

            if ($response->successful()) {
                $order->update([
                    'capi_synced' => true,
                    'capi_event_id' => $eventId,
                ]);
                Log::info("Meta CAPI Purchase Event synced for order #{$order->order_number} (Event ID: {$eventId})");
                return true;
            } else {
                Log::error("Meta CAPI API Error for order #{$order->order_number}: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Failed to send Meta CAPI Purchase event for order #{$order->id}: " . $e->getMessage());
            return false;
        }
    }
}
