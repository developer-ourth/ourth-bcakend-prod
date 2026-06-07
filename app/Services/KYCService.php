<?php

namespace App\Services;

use App\Contracts\KYCProcessorContract;
use App\Models\Vendor;
use App\Models\VendorKycDocument;
use Illuminate\Database\Eloquent\Collection;

/**
 * KYCService - Handles KYC processing and validation
 *
 * In Phase 2, this service can be extracted to a separate KYC validation
 * microservice that processes documents and validates vendor information.
 */
class KYCService implements KYCProcessorContract
{
    /**
     * Required KYC documents for vendor onboarding
     */
    private array $requiredDocuments = [
        'gst_certificate',
        'trade_license',
        'pan_card',
        'aadhar',
        'bank_statement',
    ];

    /**
     * Validate a single KYC document
     */
    public function validate(VendorKycDocument $document): bool
    {
        // Phase 1: Basic validation (document exists and has URL)
        if (!$document->document_url || !$this->isValidUrl($document->document_url)) {
            return false;
        }

        // Phase 2: Can be enhanced with:
        // - Document OCR validation
        // - Anti-fraud checks
        // - Machine learning verification
        // - External API validation (GST, PAN, etc.)

        return true;
    }

    /**
     * Process KYC submission for a vendor
     */
    public function process(Vendor $vendor): array
    {
        $documents = $vendor->kycDocuments;
        $validated = 0;
        $pending = 0;

        foreach ($documents as $doc) {
            if ($this->validate($doc)) {
                $doc->update(['status' => 'verified', 'verified_at' => now()]);
                $validated++;
            } else {
                $pending++;
            }
        }

        return [
            'vendor_id' => $vendor->id,
            'total_documents' => $documents->count(),
            'validated' => $validated,
            'pending' => $pending,
            'status' => $this->getStatus($vendor),
        ];
    }

    /**
     * Check if vendor KYC is complete (all required docs submitted)
     */
    public function isComplete(Vendor $vendor): bool
    {
        $submitted = $vendor->kycDocuments
            ->pluck('document_type')
            ->unique()
            ->toArray();

        return count(array_intersect($this->requiredDocuments, $submitted)) === count($this->requiredDocuments);
    }

    /**
     * Get KYC status for vendor
     */
    public function getStatus(Vendor $vendor): string
    {
        $documents = $vendor->kycDocuments;

        if ($documents->isEmpty()) {
            return 'not_started';
        }

        $allVerified = $documents->every(fn ($doc) => $doc->status === 'verified');
        $anyVerified = $documents->some(fn ($doc) => $doc->status === 'verified');

        if ($allVerified) {
            return 'completed';
        }

        if ($anyVerified) {
            return 'in_progress';
        }

        return 'submitted';
    }

    /**
     * Get KYC documents for vendor
     */
    public function getDocuments(Vendor $vendor): Collection
    {
        return $vendor->kycDocuments;
    }

    /**
     * Validate URL format
     */
    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
