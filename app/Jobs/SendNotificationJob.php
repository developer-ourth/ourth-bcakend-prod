<?php

namespace App\Jobs;

use App\Contracts\NotificationServiceContract;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SendNotificationJob
 *
 * Async job to send notifications to users.
 *
 * Phase 1: Queued locally
 * Phase 2: Can be consumed by a separate Notification Microservice
 *          via Kafka, RabbitMQ, or direct HTTP calls
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $event,
        public array $data,
    ) {}

    /**
     * Execute the job
     */
    public function handle(NotificationServiceContract $notificationService): void
    {
        try {
            match ($this->event) {
                'vendor.registration.confirmation' =>
                    $notificationService->sendVendorRegistrationConfirmation($this->data['user']),
                'vendor.kyc.submitted' =>
                    $notificationService->sendKYCSubmissionConfirmation($this->data['user']),
                'vendor.approved' =>
                    $notificationService->sendVendorApprovalNotification($this->data['user']),
                'vendor.rejected' =>
                    $notificationService->sendVendorRejectionNotification(
                        $this->data['user'],
                        $this->data['reason'] ?? ''
                    ),
                'order.status.updated' =>
                    $notificationService->sendOrderStatusUpdate(
                        $this->data['user'],
                        $this->data['order_id'],
                        $this->data['status']
                    ),
                default => null,
            };
        } catch (\Exception $e) {
            $this->fail($e);
        }
    }
}
