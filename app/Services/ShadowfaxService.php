<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Order;

class ShadowfaxService
{
    protected string $baseUrl;
    protected ?string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.shadowfax.base_url', 'https://dale.shadowfax.in');
        $this->token = config('services.shadowfax.api_token') ?: '';
    }

    /**
     * Push an order to Shadowfax for auto-fulfillment.
     *
     * @param Order $order
     * @return array|null Returns [awb_number, tracking_url] on success
     */
    public function createOrder(Order $order): ?array
    {
        if (!$this->token || $this->token === 'your_shadowfax_token_here') {
            Log::warning("Shadowfax API token missing. Skipping logistics fulfillment for order #{$order->id}");
            return null;
        }

        try {
            // Determine payment mode from payment relation or order property
            $gateway = strtolower($order->payment?->payment_gateway ?? $order->payment?->payment_method ?? $order->payment_method ?? '');
            $isCod = $gateway === 'cod';

            // Resolve pincode accurately from database column delivery_postal_code
            $pincodeRaw = $order->delivery_postal_code ?: ($order->delivery_pincode ?? '110001');
            $pincode = (int) preg_replace('/\D/', '', (string) $pincodeRaw) ?: 110001;

            // Build dynamic product details array from order items
            $productDetails = [];
            $items = $order->relationLoaded('items') ? $order->items : $order->items()->with('product')->get();

            if ($items && $items->count() > 0) {
                foreach ($items as $item) {
                    $skuName = $item->product_name ?? ($item->product?->name ?? 'Ourth Product');
                    $skuId = 'SKU-' . ($item->product_id ?? $item->id);
                    $itemQty = (int) ($item->quantity ?? 1);
                    $itemPrice = (float) ($item->unit_price ?: ($item->total_price ? ($item->total_price / max(1, $itemQty)) : $order->total_amount));
                    
                    $productDetails[] = [
                        'sku_id' => $skuId,
                        'sku_code' => $skuId,
                        'sku_name' => $skuName,
                        'price' => $itemPrice,
                        'quantity' => $itemQty,
                        'additional_details' => [
                            'quantity' => $itemQty,
                        ]
                    ];
                }
            } else {
                $productDetails[] = [
                    'sku_id' => 'SKU-OURTH',
                    'sku_code' => 'SKU-OURTH',
                    'sku_name' => 'Ourth Tableware',
                    'price' => (float) $order->total_amount,
                    'quantity' => 1,
                    'additional_details' => [
                        'quantity' => 1,
                    ]
                ];
            }

            // Dynamic Pickup & RTS Details from AppSetting (editable in Website Settings admin)
            $pickupName = \App\Models\AppSetting::where('key', 'pickup_name')->value('value') ?: 'Healing Ourth (Ashish Kumar)';
            $pickupPhone = \App\Models\AppSetting::where('key', 'pickup_phone')->value('value') ?: '8700209752';
            $pickupAddr1 = \App\Models\AppSetting::where('key', 'pickup_address_1')->value('value') ?: 'WZ-24 1011, Dash Gara - Todapur';
            $pickupAddr2 = \App\Models\AppSetting::where('key', 'pickup_address_2')->value('value') ?: 'Near Holi Chowk, Nr. Pusa Institute';
            $pickupCity = \App\Models\AppSetting::where('key', 'pickup_city')->value('value') ?: 'New Delhi';
            $pickupState = \App\Models\AppSetting::where('key', 'pickup_state')->value('value') ?: 'Delhi';
            $pickupPin = (int) (\App\Models\AppSetting::where('key', 'pickup_pincode')->value('value') ?: 110012);

            $payload = [
                'order_type' => 'marketplace',
                'order_details' => [
                    'client_order_id' => $order->order_number,
                    'actual_weight' => 500, // grams
                    'volumetric_weight' => 500,
                    'product_value' => (float) $order->total_amount,
                    'payment_mode' => $isCod ? 'COD' : 'Prepaid',
                    'cod_amount' => $isCod ? (float) $order->total_amount : 0,
                    'total_amount' => (float) $order->total_amount,
                    'order_service' => 'regular'
                ],
                'customer_details' => [
                    'name' => $order->delivery_name ?: ($order->user?->name ?: 'Customer'),
                    'contact' => $order->delivery_phone ?: '9999999999',
                    'address_line_1' => $order->delivery_address_line1 ?: 'Address',
                    'address_line_2' => $order->delivery_address_line2 ?: '',
                    'city' => $order->delivery_city ?: 'Mumbai',
                    'state' => $order->delivery_state ?: 'Maharashtra',
                    'pincode' => $pincode,
                ],
                'pickup_details' => [
                    'name' => $pickupName,
                    'contact' => $pickupPhone,
                    'address_line_1' => $pickupAddr1,
                    'address_line_2' => $pickupAddr2,
                    'city' => $pickupCity,
                    'state' => $pickupState,
                    'pincode' => $pickupPin,
                ],
                'rts_details' => [
                    'name' => $pickupName,
                    'contact' => $pickupPhone,
                    'address_line_1' => $pickupAddr1,
                    'address_line_2' => $pickupAddr2,
                    'city' => $pickupCity,
                    'state' => $pickupState,
                    'pincode' => $pickupPin,
                ],
                'product_details' => $productDetails
            ];

            Log::info("Shadowfax payload for order #{$order->id}: " . json_encode($payload));

            // Make HTTP POST to Shadowfax Unified API
            // Production URL: https://dale.shadowfax.in/api/v3/clients/orders/
            $response = Http::withHeaders([
                'Authorization' => "Token {$this->token}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post("{$this->baseUrl}/api/v3/clients/orders/", $payload);

            Log::info("Shadowfax response for order #{$order->id}: status={$response->status()} body={$response->body()}");

            $data = $response->json();

            if ($response->successful() && isset($data['message']) && $data['message'] === 'Success') {
                return [
                    'awb_number' => $data['data']['awb_number'] ?? null,
                    'tracking_url' => $data['data']['tracking_url'] ?? null
                ];
            } else {
                Log::error("Shadowfax API Error for order #{$order->id}: " . $response->body());
                return null;
            }

        } catch (\Exception $e) {
            Log::error("Failed to push order #{$order->id} to Shadowfax: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculate dynamic location-based delivery charge matching official Shadowfax 360 Rate Card:
     * - Zone A (Intracity / Delhi NCR): ₹39
     * - Zone B (Within North Zone - HR, UP, PB, RJ, HP, UT): ₹49
     * - Zone C/D (Metro & Rest of India): ₹59
     * - Zone E (Special Zone - NE, J&K, Islands): ₹69
     */
    public static function calculateDeliveryCharge(?string $pincode, float $subtotal = 0): float
    {
        if (empty($pincode)) {
            return 49.0;
        }

        $cleanPin = preg_replace('/\D/', '', (string) $pincode);
        if (strlen($cleanPin) < 3) {
            return 49.0;
        }

        $prefix3 = substr($cleanPin, 0, 3);
        $prefix2 = substr($cleanPin, 0, 2);

        // Zone A: Intracity (Delhi NCR: 110, 111, 112, 121, 122, 201) -> ₹39
        if (in_array($prefix3, ['110', '111', '112', '121', '122', '201'])) {
            return 39.0;
        }

        // Zone E: Special Zone (NE: 78-79, J&K: 18-19, Andaman/Lakshadweep: 74, 68) -> ₹69
        $specialZone2 = ['18', '19', '78', '79', '74', '68'];
        if (in_array($prefix2, $specialZone2)) {
            return 69.0;
        }

        // Zone B: Within North Zone (Haryana 12-13, Punjab 14-15, Chandigarh 16, UP 20-28, Rajasthan 30-34, HP 17, UT 24) -> ₹49
        $northZone2 = ['12', '13', '14', '15', '16', '17', '20', '21', '22', '23', '24', '25', '26', '27', '28', '30', '31', '32', '33', '34'];
        if (in_array($prefix2, $northZone2)) {
            return 49.0;
        }

        // Zone C/D: Metro & Rest of India -> ₹59
        return 59.0;
    }
}
