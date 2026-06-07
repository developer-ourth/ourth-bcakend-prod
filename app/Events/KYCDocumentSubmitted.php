<?php

namespace App\Events;

use App\Models\VendorKycDocument;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * KYCDocumentSubmitted Event
 *
 * Dispatched when vendor submits KYC document.
 *
 * Listeners can:
 * - Validate document
 * - Trigger anti-fraud checks
 * - Send confirmation notification
 * - Update document status
 *
 * In Phase 2: Validation and fraud checks can be separate microservices
 */
class KYCDocumentSubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public VendorKycDocument $document,
    ) {}
}
