<?php

namespace App\Jobs;

use App\Services\KYCService;
use App\Models\Vendor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * ProcessKYCDocumentsJob
 *
 * Async job to process and validate KYC documents.
 *
 * Phase 1: Validates documents locally
 * Phase 2: Can delegate to KYC validation microservice for:
 *          - OCR extraction
 *          - Anti-fraud detection
 *          - External API verification (GST, PAN, etc.)
 */
class ProcessKYCDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Vendor $vendor,
    ) {}

    /**
     * Execute the job
     */
    public function handle(KYCService $kycService): void
    {
        try {
            $kycService->process($this->vendor);
        } catch (\Exception $e) {
            $this->fail($e);
        }
    }
}
