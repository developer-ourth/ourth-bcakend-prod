<?php

namespace App\Events;

use App\Models\Vendor;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * VendorRejected Event
 *
 * Dispatched when vendor application is rejected.
 *
 * Listeners can:
 * - Send rejection notification with reason
 * - Log rejection details for analytics
 * - Update vendor status
 */
class VendorRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Vendor $vendor,
    ) {}
}
