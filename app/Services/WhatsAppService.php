<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\Cart;
use App\Models\AppSetting;

class WhatsAppService
{
    protected ?string $phoneNumberId;
    protected ?string $accessToken;

    public function __construct()
    {
        $this->phoneNumberId = config('services.whatsapp.phone_number_id')
            ?: (AppSetting::where('key', 'whatsapp_phone_number_id')->value('value') ?: env('WHATSAPP_PHONE_NUMBER_ID'));

        $this->accessToken = config('services.whatsapp.access_token')
            ?: (AppSetting::where('key', 'whatsapp_access_token')->value('value') ?: env('WHATSAPP_ACCESS_TOKEN'));
    }

    /**
     * Normalizes phone number to E.164 format without leading '+' (e.g. 918700209752)
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
     * Send Order Confirmation & Live Tracking via WhatsApp
     */
    public function sendOrderConfirmation(Order $order): bool
    {
        $phone = $this->formatPhone($order->delivery_phone ?: ($order->user?->phone_number ?: ''));
        if (!$phone) {
            Log::warning("WhatsApp skip: No phone number for order #{$order->id}");
            return false;
        }

        $customerName = $order->delivery_name ?: ($order->user?->name ?: 'Customer');
        $orderNumber = $order->order_number;
        $totalAmount = number_format($order->total_amount, 2);
        $awbNumber = $order->awb_number ?: 'Processing';
        $trackingUrl = $order->tracking_url ?: 'https://www.healingourth.com/account/orders';

        $message = "🌱 *Order Confirmed! Thank you for choosing OURTH.*\n\n"
                 . "Hi {$customerName},\n"
                 . "Your order *#{$orderNumber}* (₹{$totalAmount}) has been confirmed!\n\n"
                 . "📦 *Shipping Status:* Out for delivery\n"
                 . "🚚 *Shadowfax AWB:* {$awbNumber}\n"
                 . "🔗 *Track Live:* {$trackingUrl}\n\n"
                 . "Together, we are building a plastic-free, sustainable Bharat! 🌿";

        return $this->sendMessage($phone, $message);
    }

    /**
     * Send Abandoned Cart Recovery Message via WhatsApp
     */
    public function sendAbandonedCartReminder(string $rawPhone, string $customerName = 'there', string $cartUrl = 'https://www.healingourth.com/cart'): bool
    {
        $phone = $this->formatPhone($rawPhone);
        if (!$phone) return false;

        $message = "🌿 *Hi {$customerName}, you left items in your OURTH cart!*\n\n"
                 . "Your 100% natural, eco-friendly tableware is waiting for you.\n\n"
                 . "🎁 Use code *GREEN10* for an extra 10% OFF + Earn 5 Green Points per ₹100 spent!\n\n"
                 . "👉 *Complete your order now:* {$cartUrl}\n\n"
                 . "Need help? Reply to this message anytime!";

        return $this->sendMessage($phone, $message);
    }

    /**
     * Core Meta WhatsApp Cloud API Dispatcher
     */
    public function sendMessage(string $recipientPhone, string $textMessage): bool
    {
        return $this->sendRichMediaMessage($recipientPhone, $textMessage);
    }

    /**
     * Rich Media & Interactive CTA Meta WhatsApp Cloud API Dispatcher
     */
    public function sendRichMediaMessage(
        string $recipientPhone,
        string $textMessage,
        string $mediaType = 'none',
        ?string $mediaUrl = null,
        string $ctaType = 'none'
    ): bool {
        if (!$this->phoneNumberId || !$this->accessToken) {
            Log::warning("WhatsApp API Credentials missing for phone_number_id or access_token.");
            return false;
        }

        try {
            $endpoint = "https://graph.facebook.com/v19.0/{$this->phoneNumberId}/messages";
            $formattedPhone = $this->formatPhone($recipientPhone);

            if ($mediaType === 'image' && !empty($mediaUrl)) {
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $formattedPhone,
                    'type' => 'image',
                    'image' => [
                        'link' => $mediaUrl,
                        'caption' => $textMessage,
                    ],
                ];
            } elseif ($mediaType === 'video' && !empty($mediaUrl)) {
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $formattedPhone,
                    'type' => 'video',
                    'video' => [
                        'link' => $mediaUrl,
                        'caption' => $textMessage,
                    ],
                ];
            } elseif ($ctaType !== 'none') {
                $buttons = [];
                if ($ctaType === 'shop_now') {
                    $buttons[] = [
                        'type' => 'reply',
                        'reply' => ['id' => 'btn_shop_now', 'title' => '🛒 Shop Now'],
                    ];
                } elseif ($ctaType === 'get_quote') {
                    $buttons[] = [
                        'type' => 'reply',
                        'reply' => ['id' => 'btn_get_quote', 'title' => '📞 Get Quote'],
                    ];
                }

                $payload = [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $formattedPhone,
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'button',
                        'body' => ['text' => $textMessage],
                        'action' => ['buttons' => $buttons],
                    ],
                ];
            } else {
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $formattedPhone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => true,
                        'body' => $textMessage,
                    ],
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'Content-Type' => 'application/json',
            ])->post($endpoint, $payload);

            if ($response->successful()) {
                Log::info("WhatsApp rich media message successfully sent to {$formattedPhone}");
                return true;
            } else {
                Log::error("WhatsApp API Error for {$formattedPhone}: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp rich media message to {$recipientPhone}: " . $e->getMessage());
            return false;
        }
    }
}
