<?php

namespace App\Services;

use App\Contracts\QRCodeGeneratorContract;
use App\Models\Vendor;
use App\Models\VendorQrCode;
use Illuminate\Support\Str;

/**
 * QRCodeService - Handles QR code generation and retrieval
 *
 * This service encapsulates all QR code logic. In Phase 2, this can be
 * extracted to a separate microservice by implementing a client that calls
 * the remote service instead.
 */
class QRCodeService implements QRCodeGeneratorContract
{
    /**
     * Generate QR code for vendor after approval
     */
    public function generate(Vendor $vendor): array
    {
        // Check if already exists
        $existingQR = VendorQrCode::where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->first();

        if ($existingQR) {
            return [
                'qr_code_id' => $existingQR->qr_code_id,
                'qr_code_image_url' => $existingQR->qr_code_image_url,
                'status' => $existingQR->status,
            ];
        }

        // Generate unique QR ID
        $qrId = 'QR_' . $vendor->id . '_' . Str::random(12);

        // Generate QR code image URL
        // Using QR code generation API (e.g., qr-server.com) or library
        $qrImageUrl = $this->generateQRCodeImage($qrId);

        // Store in database
        $qrCode = VendorQrCode::create([
            'vendor_id' => $vendor->id,
            'qr_code_id' => $qrId,
            'qr_code_image_url' => $qrImageUrl,
            'status' => 'active',
            'generated_at' => now(),
        ]);

        return [
            'qr_code_id' => $qrCode->qr_code_id,
            'qr_code_image_url' => $qrCode->qr_code_image_url,
            'status' => $qrCode->status,
        ];
    }

    /**
     * Get active QR code for vendor
     */
    public function getQRCode(Vendor $vendor): ?array
    {
        $qr = $vendor->activeQrCode;

        if (!$qr) {
            return null;
        }

        return [
            'qr_code_id' => $qr->qr_code_id,
            'qr_code_image_url' => $qr->qr_code_image_url,
            'status' => $qr->status,
            'scans_count' => $qr->scans_count,
        ];
    }

    /**
     * Delete/deactivate QR code
     */
    public function delete(Vendor $vendor): bool
    {
        return VendorQrCode::where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->update(['status' => 'inactive']) > 0;
    }

    /**
     * Generate QR code image using external API or library
     *
     * In Phase 2, this can be delegated to a separate service
     */
    private function generateQRCodeImage(string $qrId): string
    {
        // Using QR Server API (free, no auth needed)
        // In production, use a QR library or service
        $encodedData = urlencode(json_encode([
            'vendor_id' => $qrId,
            'platform' => 'OURTH',
            'timestamp' => now()->toIso8601String(),
        ]));

        return "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$encodedData}";
    }
}
