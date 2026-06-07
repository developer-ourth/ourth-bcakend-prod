<?php

namespace App\Contracts;

use App\Models\User;

/**
 * NotificationServiceContract - Abstraction for all notifications
 *
 * Phase 1: Send via email/SMS directly
 * Phase 2: Queue-based, extractable to separate notification microservice
 */
interface NotificationServiceContract
{
    /**
     * Send vendor registration confirmation
     */
    public function sendVendorRegistrationConfirmation(User $user): void;

    /**
     * Send KYC submission confirmation
     */
    public function sendKYCSubmissionConfirmation(User $user): void;

    /**
     * Send vendor approval notification
     */
    public function sendVendorApprovalNotification(User $user): void;

    /**
     * Send vendor rejection notification
     */
    public function sendVendorRejectionNotification(User $user, string $reason): void;

    /**
     * Send order status update
     */
    public function sendOrderStatusUpdate(User $user, string $orderId, string $status): void;
}
