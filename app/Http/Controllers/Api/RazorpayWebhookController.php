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

        // Also search by transaction_id in payments table if not found
        if (!$order) {
            $payment = \App\Models\Payment::where('transaction_id', $razorpayOrderId)->first();
            if ($payment) {
                $order = $payment->order;
            }
        }

        if (!$order) {
            Log::warning("Razorpay Webhook: No order found for razorpay_order_id={$razorpayOrderId}");
            return;
        }

        // Confirm the order if still pending
        if ($order->order_status === 'pending') {
            $order->update(['order_status' => 'confirmed']);
            Log::info("Order {$order->id} marked as confirmed via Razorpay Webhook.");
        }

        // Update payment status
        $order->update(['payment_status' => 'paid']);

        // Push to Shadowfax if not already done (regardless of previous status)
        if (!$order->awb_number) {
            $shadowfax = new ShadowfaxService();
            $logisticsInfo = $shadowfax->createOrder($order->fresh()->load('items.product', 'payment'));
            if ($logisticsInfo) {
                $order->update([
                    'awb_number'   => $logisticsInfo['awb_number'],
                    'tracking_url' => $logisticsInfo['tracking_url'],
                ]);
                Log::info("Shadowfax shipment created for order #{$order->id} via Razorpay Webhook. AWB: {$logisticsInfo['awb_number']}");
            }
        } else {
            Log::info("Order #{$order->id} already has AWB {$order->awb_number} — skipping Shadowfax push.");
        }
    }
}
