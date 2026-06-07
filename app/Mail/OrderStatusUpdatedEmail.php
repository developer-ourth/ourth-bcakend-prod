<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdatedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $orderId,
        public readonly string $status,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Order #{$this->orderId} – Status Update: {$this->status}");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.order-status-updated',
            with: [
                'name'    => $this->user->name,
                'orderId' => $this->orderId,
                'status'  => $this->status,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
