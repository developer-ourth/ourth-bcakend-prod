<?php

namespace App\Services;

use App\Contracts\NotificationServiceContract;
use App\Mail\OrderStatusUpdatedEmail;
use App\Mail\VendorApprovedEmail;
use App\Mail\VendorKycSubmittedEmail;
use App\Mail\VendorRejectedEmail;
use App\Mail\VendorWelcomeEmail;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * NotificationService - Centralized notification handling
 *
 * All notifications are queued via Laravel's mail queue. Set QUEUE_CONNECTION
 * in .env to 'database' or 'redis' for async delivery, or keep 'sync' for
 * immediate dispatch (useful in development with MAIL_MAILER=log).
 */
class NotificationService implements NotificationServiceContract
{
    public function sendVendorRegistrationConfirmation(User $user): void
    {
        $vendor = Vendor::where('user_id', $user->id)->first();

        if (! $vendor) {
            Log::warning('sendVendorRegistrationConfirmation: no vendor found for user', ['user_id' => $user->id]);

            return;
        }

        $this->dispatch($user, new VendorWelcomeEmail($vendor, $user));
    }

    public function sendKYCSubmissionConfirmation(User $user): void
    {
        $this->dispatch($user, new VendorKycSubmittedEmail($user));
    }

    public function sendVendorApprovalNotification(User $user): void
    {
        $this->dispatch($user, new VendorApprovedEmail($user));
    }

    public function sendVendorRejectionNotification(User $user, string $reason): void
    {
        $this->dispatch($user, new VendorRejectedEmail($user, $reason));
    }

    public function sendOrderStatusUpdate(User $user, string $orderId, string $status): void
    {
        $this->dispatch($user, new OrderStatusUpdatedEmail($user, $orderId, $status));
    }

    private function dispatch(User $user, \Illuminate\Mail\Mailable $mailable): void
    {
        try {
            Mail::to($user->email)->queue($mailable);
        } catch (\Exception $e) {
            Log::error('Failed to queue notification email', [
                'user_id' => $user->id,
                'mailable' => get_class($mailable),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
