<?php

namespace App\Contracts;

use App\Models\Vendor;

/**
 * QRCodeGeneratorContract - Abstraction for QR code generation
 *
 * This contract allows swapping QR generation implementations:
 * - Phase 1: Local implementation
 * - Phase 2: Separate microservice
 */
interface QRCodeGeneratorContract
{
    /**
     * Generate QR code for vendor
     */
    public function generate(Vendor $vendor): array;

    /**
     * Get QR code for vendor
     */
    public function getQRCode(Vendor $vendor): ?array;

    /**
     * Delete QR code
     */
    public function delete(Vendor $vendor): bool;
}
