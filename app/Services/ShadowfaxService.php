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
                    'name' => 'Ourth Warehouse',
                    'contact' => '9999999999',
                    'address_line_1' => 'Ourth Warehouse, Sector 14',
                    'address_line_2' => 'Dwarka',
                    'city' => 'Delhi',
                    'state' => 'Delhi',
                    'pincode' => 110078,
                ],
                'rts_details' => [
                    'name' => 'Ourth Returns',
                    'contact' => '9999999999',
                    'address_line_1' => 'Ourth Returns, Sector 14',
                    'address_line_2' => 'Dwarka',
                    'city' => 'Delhi',
                    'state' => 'Delhi',
                    'pincode' => 110078,
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
     * Calculate dynamic location-based delivery charge using Shadowfax distance slabs.
     *
     * Pickup Hub: 110078 (Delhi NCR)
     * - Delhi NCR Local (110xxx, 121-122 Gurgaon/Faridabad, 201 Noida/Ghaziabad): ₹35
     * - Metro Hubs & North India (Mumbai 400, Bangalore 560, Kolkata 700, Chennai 600, Hyd 500, UP 20-28, HR 12-13, PB 14-15): ₹50
     * - Rest of India: ₹70
     */
    public static function calculateDeliveryCharge(?string $pincode, float $subtotal = 0): float
    {
        if (empty($pincode)) {
            return 40.0;
        }

        $cleanPin = preg_replace('/\D/', '', (string) $pincode);
        if (strlen($cleanPin) < 3) {
            return 40.0;
        }

        $prefix3 = substr($cleanPin, 0, 3);
        $prefix2 = substr($cleanPin, 0, 2);

        // Delhi NCR local slab (Delhi 110, Gurgaon 122, Noida/Ghaziabad 201, Faridabad 121)
        if (in_array($prefix3, ['110', '111', '112', '121', '122', '201'])) {
            return 35.0;
        }

        // Metro Hubs & Surrounding North India region slab (Mumbai, Bangalore, Kolkata, Chennai, Hyderabad, UP, HR, PB, RJ)
        $metroPrefixes = ['400', '401', '402', '700', '600', '560', '500', '302'];
        $northRegion2 = ['12', '13', '14', '15', '16', '20', '21', '22', '23', '24', '25', '26', '27', '28', '30', '31'];
        if (in_array($prefix3, $metroPrefixes) || in_array($prefix2, $northRegion2)) {
            return 50.0;
        }

        // Rest of India national slab
        return 70.0;
    }
}
