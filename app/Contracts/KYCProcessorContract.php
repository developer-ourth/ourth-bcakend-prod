<?php

namespace App\Contracts;

use App\Models\Vendor;
use App\Models\VendorKycDocument;

/**
 * KYCProcessorContract - Abstraction for KYC processing
 *
 * Phase 1: Synchronous processing
 * Phase 2: Extractable to KYC validation microservice
 */
interface KYCProcessorContract
{
    /**
     * Validate KYC document
     */
    public function validate(VendorKycDocument $document): bool;

    /**
     * Process KYC submission
     */
    public function process(Vendor $vendor): array;

    /**
     * Check if vendor KYC is complete
     */
    public function isComplete(Vendor $vendor): bool;

    /**
     * Get KYC status
     */
    public function getStatus(Vendor $vendor): string;
}
