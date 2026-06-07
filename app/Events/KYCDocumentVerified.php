<?php

namespace App\Events;

use App\Models\VendorKycDocument;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * KYCDocumentVerified Event
 *
 * Dispatched when KYC document is verified.
 *
 * Listeners can:
 * - Check if all required docs are verified
 * - Trigger vendor approval workflow
 * - Update analytics
 */
class KYCDocumentVerified
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public VendorKycDocument $document,
    ) {}
}
