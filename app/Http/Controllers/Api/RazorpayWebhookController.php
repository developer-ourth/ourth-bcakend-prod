<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ShadowfaxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RazorpayWebhookController extends Controller
{
    /**
     * Handle incoming Razorpay Webhook events.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        $webhookSecret = config('services.razorpay.webhook_secret');
        $signature = $request->header('X-Razorpay-Signature');
        $payload = $request->getContent();
        
        if (!$webhookSecret || !$signature) {
            Log::warning('Razorpay Webhook secret or signature missing.');
            return response()->json(['status' => 'error', 'message' => 'Missing signature'], 400);
        }

        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('Razorpay Webhook signature mismatch.');
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
        }

        $data = json_decode($payload, true);

        if (!isset($data['event'])) {
            return response()->json(['status' => 'ok']); // Acknowledge invalid event silently
        }

        switch ($data['event']) {
            case 'order.paid':
                $this->handleOrderPaid($data['payload']['order']['entity']);
                break;
            // Handle other events as needed
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle the order.paid event from Razorpay.
     *
     * @param array $orderEntity
     * @return void
     */
    protected function handleOrderPaid(array $orderEntity)
    {
        $razorpayOrderId = $orderEntity['id'] ?? null;
        
        if (!$razorpayOrderId) {
            return;
        }

        $order = Order::where('razorpay_order_id', $razorpayOrderId)->first();

        if ($order && $order->order_status === 'pending') {
            $order->update([
                'order_status' => 'confirmed'
            ]);
            Log::info("Order {$order->id} marked as confirmed via Razorpay Webhook.");
            
            // Auto-fulfill via Shadowfax
            $shadowfax = new ShadowfaxService();
            $logisticsInfo = $shadowfax->createOrder($order);
            if ($logisticsInfo) {
                $order->update([
                    'awb_number' => $logisticsInfo['awb_number'],
                    'tracking_url' => $logisticsInfo['tracking_url']
                ]);
            }
        }
    }
}
