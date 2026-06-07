<?php

namespace App\Events;

use App\Models\Vendor;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * VendorApproved Event
 *
 * Dispatched when vendor is approved by admin.
 *
 * Listeners can:
 * - Generate QR code
 * - Send approval notification
 * - Unlock vendor features
 * - Update vendor status
 *
 * In Phase 2: QR generation and notifications can be separate services
 */
class VendorApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Vendor $vendor,
    ) {}
}
