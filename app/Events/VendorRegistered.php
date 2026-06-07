<?php

namespace App\Events;

use App\Models\Vendor;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * VendorRegistered Event
 *
 * Dispatched when vendor registration is complete.
 *
 * Listeners can:
 * - Send welcome email
 * - Trigger onboarding workflows
 * - Log audit trail
 * - Update analytics
 *
 * In Phase 2: Listeners can be separate microservices consuming events
 */
class VendorRegistered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Vendor $vendor,
        public User $user,
    ) {}
}
