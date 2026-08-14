<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Order;

class ShadowfaxWebhookController extends Controller
{
    /**
     * Handle incoming webhooks from Shadowfax.
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->all();
        
        // Log the incoming payload for auditing
        Log::info('Shadowfax Webhook Received:', $payload);

        // Shadowfax sends `client_order_id` in their payload
        $clientOrderId = $payload['client_order_id'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$clientOrderId) {
            return response()->json(['error' => 'Missing client_order_id'], 400);
        }

        // Parse our actual order ID from the client_order_id (e.g. "ORD-123" -> "123")
        $orderId = str_replace('ORD-', '', $clientOrderId);

        $order = Order::find($orderId);

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        // Map Shadowfax statuses to our database statuses
        // Note: Shadowfax statuses vary (e.g., 'In Transit', 'Out for Delivery', 'Delivered', 'RTO')
        // We'll normalize it by converting to lowercase for comparison.
        $normalizedStatus = strtolower($status);

        if (in_array($normalizedStatus, ['in transit', 'out for delivery', 'shipped'])) {
            $order->order_status = 'dispatched';
            if (!$order->dispatched_at) {
                $order->dispatched_at = now();
            }
        } elseif (in_array($normalizedStatus, ['delivered'])) {
            $order->order_status = 'delivered';
            if (!$order->delivered_at) {
                $order->delivered_at = now();
            }
        } elseif (str_contains($normalizedStatus, 'cancelled') || str_contains($normalizedStatus, 'rto')) {
            $order->order_status = 'cancelled';
            $order->cancellation_reason = "Cancelled/RTO by logistics: " . ($payload['sub_status'] ?? 'Unknown');
            if (!$order->cancelled_at) {
                $order->cancelled_at = now();
            }
        }

        $order->save();

        return response()->json(['message' => 'Webhook processed successfully'], 200);
    }
}
