<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;
use App\Models\User;

class OrderPlacedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Order #{$this->order->order_number} Confirmed – Thank you!");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.order-placed',
            with: [
                'name'        => $this->user->name,
                'orderNumber' => $this->order->order_number,
                'orderType'   => $this->order->order_type ?? 'b2c',
                'total'       => number_format((float) $this->order->total_amount, 2),
                'itemCount'   => $this->order->items_count ?? $this->order->items->count(),
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
