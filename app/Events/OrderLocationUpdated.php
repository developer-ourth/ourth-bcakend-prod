<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderLocationUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Order $order,
        public float $riderLat,
        public float $riderLng,
        public float $bearing,
        public ?string $statusMessage,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('order.' . $this->order->id)];
    }

    public function broadcastAs(): string
    {
        return 'location.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id'       => $this->order->id,
            'rider_lat'      => $this->riderLat,
            'rider_lng'      => $this->riderLng,
            'bearing'        => $this->bearing,
            'status_message' => $this->statusMessage,
            'updated_at'     => now()->toIso8601String(),
        ];
    }
}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
