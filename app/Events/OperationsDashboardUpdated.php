<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OperationsDashboardUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $entity,
        public ?int $entityId = null,
        public string $action = 'updated',
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('operations.dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'operations.dashboard.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'entity' => $this->entity,
            'entity_id' => $this->entityId,
            'action' => $this->action,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
