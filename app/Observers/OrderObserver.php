<?php

namespace App\Observers;

use App\Events\OperationsDashboardUpdated;
use App\Mail\OrderPlacedEmail;
use App\Mail\OrderStatusUpdatedEmail;
use App\Models\Notification;
use App\Models\Order;
use App\Services\ExpoPushService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function __construct(private readonly ExpoPushService $push) {}

    /**
     * Notify the consumer when their order is first created (placed).
     */
    public function created(Order $order): void
    {
        event(new OperationsDashboardUpdated('order', $order->id, 'created'));

        if (! $order->user_id) {
            return;
        }

        $title   = 'Order placed';
        $message = "Your order #{$order->order_number} has been placed and is awaiting confirmation.";

        Notification::create([
            'user_id' => $order->user_id,
            'type'    => 'order_placed',
            'title'   => $title,
            'message' => $message,
            'data'    => ['order_id' => $order->id, 'order_number' => $order->order_number],
            'is_read' => false,
        ]);

        $this->push->notifyUser($order->user_id, $title, $message, [
            'type'         => 'order_placed',
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
        ]);

        // Send confirmation email to Customer & instant alert email to Admin
        $user = $order->user;
        $adminEmail = env('ADMIN_NOTIFICATION_EMAIL', 'hoiplorders@gmail.com');

        if ($user?->email) {
            try {
                Mail::to($user->email)->send(new OrderPlacedEmail($user, $order->loadCount('items')));
            } catch (\Throwable $e) {
                Log::error("Failed to send order placed email to customer for order #{$order->id}: " . $e->getMessage());
            }
        }

        if ($adminEmail) {
            try {
                $custName = $user->name ?? 'Customer';
                $rawEmail = $user->email ?? '';
                $custEmail = ($rawEmail && !str_contains($rawEmail, 'test12') && !str_contains($rawEmail, 'phone_') && !str_contains($rawEmail, '@example.com')) 
                    ? $rawEmail 
                    : "Not provided (Mobile: {$order->delivery_phone})";
                Mail::raw(
                    "🎉 NEW ORDER PLACED ON OURTH!\n\n" .
                    "Order Number: #{$order->order_number}\n" .
                    "Customer: {$custName} ({$custEmail})\n" .
                    "Phone: {$order->delivery_phone}\n" .
                    "Total Payment: ₹" . number_format((float) $order->total_amount, 2) . "\n" .
                    "Payment Method: " . strtoupper($order->payment?->payment_method ?? 'COD') . "\n" .
                    "Delivery Address: {$order->delivery_address_line1}, {$order->delivery_city}, {$order->delivery_state} - {$order->delivery_postal_code}\n\n" .
                    "Check your Admin Dashboard for full details.",
                    function ($msg) use ($adminEmail, $order) {
                        $msg->to($adminEmail)
                            ->subject("🎉 New Order #{$order->order_number} - ₹" . number_format((float) $order->total_amount, 2));
                    }
                );
            } catch (\Throwable $e) {
                Log::error("Failed to send admin order notification email for order #{$order->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Fire targeted notifications whenever the order_status changes.
     */
    public function updated(Order $order): void
    {
        if ($order->isDirty('order_status')) {
            event(new OperationsDashboardUpdated('order', $order->id, 'updated'));
        }

        if (! $order->isDirty('order_status') || ! $order->user_id) {
            return;
        }

        $status = $order->order_status;

        $map = [
            'confirmed'  => ['order_confirmed',   'Order confirmed',   "Great news! Your order #{$order->order_number} has been confirmed."],
            'dispatched' => ['order_dispatched',   'Order dispatched',  "Your order #{$order->order_number} is on its way."],
            'delivered'  => ['order_delivered',    'Order delivered',   "Your order #{$order->order_number} has been delivered. Enjoy!"],
            'cancelled'  => ['order_cancelled',    'Order cancelled',   "Your order #{$order->order_number} has been cancelled."],
        ];

        if (! isset($map[$status])) {
            return;
        }

        [$type, $title, $message] = $map[$status];

        Notification::create([
            'user_id' => $order->user_id,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'data'    => ['order_id' => $order->id, 'order_number' => $order->order_number, 'status' => $status],
            'is_read' => false,
        ]);

        $this->push->notifyUser($order->user_id, $title, $message, [
            'type'         => $type,
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'status'       => $status,
        ]);

        // Queue status update email
        $user = $order->user;
        if ($user?->email) {
            try {
                Mail::to($user->email)->queue(new OrderStatusUpdatedEmail($user, $order->order_number, $status));
            } catch (\Throwable $e) {
                Log::error("Failed to queue order status updated email for order #{$order->id}: " . $e->getMessage());
            }
        }
    }
}
