<?php

namespace App\Jobs;

use App\Services\QRCodeService;
use App\Models\Vendor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * GenerateVendorQRCodeJob
 *
 * Async job to generate QR code for vendor after approval.
 *
 * Phase 1: Can be run synchronously or queued
 * Phase 2: In microservices architecture, this job would communicate
 *          with the QR Code microservice via HTTP or message queue
 */
class GenerateVendorQRCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Vendor $vendor,
    ) {}

    /**
     * Execute the job
     */
    public function handle(QRCodeService $qrService): void
    {
        try {
            $qrService->generate($this->vendor);
        } catch (\Exception $e) {
            // Log error and retry
            $this->fail($e);
        }
    }
}
