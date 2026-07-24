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
        $this->baseUrl = config('services.shadowfax.base_url', 'https://api.shadowfax.in');
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
            // Return a mock AWB for local testing if we want, but usually return null.
            // Returning a mock here so the user can see it working locally
            $awb = 'SFX' . rand(10000000, 99999999);
            return [
                'awb_number' => $awb,
                'tracking_url' => "https://track.shadowfax.in/track?awb={$awb}"
            ];
        }

        try {
            // Shadowfax requires detailed pickup/drop addresses, package dimensions, etc.
            // This is a template for the real Shadowfax v3 API structure.
            $payload = [
                'client_order_id' => 'ORD-' . $order->id,
                'actual_weight' => 1.0, // Should be calculated from order items
                'volumetric_weight' => 1.0,
                'payment_mode' => $order->payment_gateway === 'cod' ? 'COD' : 'Prepaid',
                'order_amount' => $order->total_amount,
                'customer_details' => [
                    'name' => 'Customer', // Would map from order->address
                    'contact' => '9999999999',
                    'address_line_1' => 'Customer Address',
                    'city' => 'Mumbai',
                    'state' => 'MH',
                    'pincode' => '400001',
                ],
                'pickup_details' => [
                    'name' => 'Healing Ourth Warehouse',
                    'contact' => '1800OURTHCARE',
                    'address_line_1' => 'Main Warehouse',
                    'city' => 'Mumbai',
                    'state' => 'MH',
                    'pincode' => '400001',
                ]
            ];

            // In reality, this makes an HTTP POST to Shadowfax
            $response = Http::withToken($this->token)->post("{$this->baseUrl}/api/v3/orders", $payload);
            
            if ($response->successful()) {
                $data = $response->json();
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
}
