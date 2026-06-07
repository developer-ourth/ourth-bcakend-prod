<?php

namespace App\Services;

use App\Events\VendorApproved;
use App\Events\VendorRegistered;
use App\Events\VendorRejected;
use App\Mail\VendorWelcomeEmail;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorApproval;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * VendorService - Core vendor business logic
 *
 * Encapsulates vendor registration, approval workflows, and lifecycle management.
 * Makes it easy to extract vendor management to a separate microservice in Phase 2.
 */
class VendorService
{
    /**
     * Register a new vendor
     */
    public function register(array $data): array
    {
        // Create user account
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'user_type' => 'vendor',
            'role' => 'vendor',
            'status' => 'active',
        ]);

        // Create vendor profile
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'vendor_code' => $this->generateUniqueVendorCode(),
            'business_name' => $data['business_name'],
            'gstin' => $data['gstin'] ?? null,
            'trade_license_number' => $data['trade_license_number'] ?? null,
            'address_line1' => $data['address_line1'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'kyc_status' => 'pending',
        ]);

        // Create approval workflow entry
        VendorApproval::create([
            'vendor_id' => $vendor->id,
            'approval_stage' => 'pending_documents',
        ]);

        // Dispatch event for other services to listen to
        // In Phase 2: This can trigger notifications, QR generation, etc.
        event(new VendorRegistered($vendor, $user));

        // Send welcome email containing the vendor's unique login ID
        Mail::to($user->email)->queue(new VendorWelcomeEmail($vendor, $user));

        return [
            'user' => $user,
            'vendor' => $vendor,
        ];
    }

    /** Generate a unique random 6-digit vendor code. */
    private function generateUniqueVendorCode(): string
    {
        do {
            $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        } while (Vendor::where('vendor_code', $code)->exists());

        return $code;
    }

    /**
     * Approve a vendor
     */
    public function approve(Vendor $vendor, User $admin, string $notes = ''): bool
    {
        $approval = $vendor->approval;

        if (! $approval) {
            return false;
        }

        $approval->update([
            'approval_stage' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'approval_notes' => $notes,
        ]);

        // Update vendor status - use 'verified' enum value
        $vendor->update([
            'kyc_status' => 'verified',
            'kyc_verified_at' => now(),
            'kyc_verified_by' => $admin->id,
        ]);

        // Dispatch approval event
        event(new VendorApproved($vendor));

        return true;
    }

    /**
     * Reject a vendor
     */
    public function reject(Vendor $vendor, User $admin, string $reason): bool
    {
        $approval = $vendor->approval;

        if (! $approval) {
            return false;
        }

        $approval->update([
            'approval_stage' => 'rejected',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        // Update vendor status
        $vendor->update(['kyc_status' => 'rejected']);

        // Dispatch rejection event
        event(new VendorRejected($vendor));

        return true;
    }

    /**
     * Get vendor approval status with detailed information
     */
    public function getApprovalStatus(Vendor $vendor): array
    {
        $approval = $vendor->approval;

        return [
            'vendor_id' => $vendor->id,
            'vendor_name' => $vendor->business_name,
            'approval_stage' => $approval?->approval_stage,
            'kyc_status' => $vendor->kyc_status,
            'updated_at' => $approval?->updated_at,
            'reviewed_by' => $approval?->reviewer?->name,
            'rejection_reason' => $approval?->rejection_reason,
        ];
    }

    /**
     * Update vendor profile
     */
    public function updateProfile(Vendor $vendor, array $data): Vendor
    {
        $vendor->update($data);

        return $vendor->fresh();
    }

    /**
     * Get vendor with all related data
     */
    public function getWithRelations(Vendor $vendor): Vendor
    {
        return $vendor->load([
            'user',
            'approval',
            'kycDocuments',
            'activeQrCode',
        ]);
    }
}
