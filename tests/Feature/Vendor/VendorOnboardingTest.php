<?php

namespace Tests\Feature\Vendor;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VendorOnboardingTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────
    // Registration
    // ──────────────────────────────────────────────────────────

    public function test_vendor_can_register_with_valid_data(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/vendors/register', [
            'name'          => 'Kumar Vendor',
            'email'         => 'vendor@example.com',
            'phone'         => '9876543210',
            'password'      => 'password123',
            'business_name' => 'Kumar Electronics',
            'gstin'         => '27AABCT1234H1Z5',
            'city'          => 'Mumbai',
            'state'         => 'Maharashtra',
            'postal_code'   => '400001',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['vendor_id', 'vendor_code']]);

        $this->assertDatabaseHas('users', ['email' => 'vendor@example.com']);
        $this->assertDatabaseHas('vendors', ['business_name' => 'Kumar Electronics']);
    }

    public function test_vendor_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com', 'phone' => '9000000000']);

        $this->postJson('/api/v1/vendors/register', [
            'name'          => 'Another Vendor',
            'email'         => 'taken@example.com',
            'phone'         => '9876543210',
            'password'      => 'password123',
            'business_name' => 'Shop',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_vendor_register_fails_with_duplicate_phone(): void
    {
        User::factory()->create(['email' => 'other@example.com', 'phone' => '9876543210']);

        $this->postJson('/api/v1/vendors/register', [
            'name'          => 'Another Vendor',
            'email'         => 'new@example.com',
            'phone'         => '9876543210',
            'password'      => 'password123',
            'business_name' => 'Shop',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    // ──────────────────────────────────────────────────────────
    // KYC Upload
    // ──────────────────────────────────────────────────────────

    public function test_vendor_can_upload_kyc_document(): void
    {
        Mail::fake();

        $vendor = Vendor::factory()->create();

        $this->postJson('/api/v1/vendors/kyc/upload', [
            'vendor_id'     => $vendor->id,
            'document_type' => 'gst_certificate',
            'document_url'  => 'https://example.com/docs/gst.pdf',
        ])->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('vendor_kyc_documents', [
            'vendor_id'     => $vendor->id,
            'document_type' => 'gst_certificate',
            'status'        => 'submitted',
        ]);
    }

    public function test_kyc_upload_fails_with_invalid_document_type(): void
    {
        $vendor = Vendor::factory()->create();

        $this->postJson('/api/v1/vendors/kyc/upload', [
            'vendor_id'     => $vendor->id,
            'document_type' => 'selfie',
            'document_url'  => 'https://example.com/docs/photo.jpg',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['document_type']);
    }

    // ──────────────────────────────────────────────────────────
    // Approval
    // ──────────────────────────────────────────────────────────

    public function test_admin_can_approve_a_vendor(): void
    {
        Mail::fake();

        $admin  = User::factory()->create(['role' => 'admin', 'user_type' => 'admin']);
        $vendor = Vendor::factory()->create(['kyc_status' => 'pending']);
        $token  = $admin->createToken('dashboard')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/vendors/{$vendor->id}/approve", [
            'notes' => 'All documents verified',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('vendors', ['id' => $vendor->id, 'kyc_status' => 'verified']);
    }

    public function test_non_admin_cannot_approve_a_vendor(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $vendor   = Vendor::factory()->create(['kyc_status' => 'pending']);
        $token    = $consumer->createToken('dashboard')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/vendors/{$vendor->id}/approve", [
            'notes' => 'Trying to approve',
        ])->assertStatus(403);
    }

    public function test_admin_can_reject_a_vendor(): void
    {
        Mail::fake();

        $admin  = User::factory()->create(['role' => 'admin', 'user_type' => 'admin']);
        $vendor = Vendor::factory()->create(['kyc_status' => 'pending']);
        $token  = $admin->createToken('dashboard')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/vendors/{$vendor->id}/reject", [
            'reason' => 'Invalid documents',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('vendors', ['id' => $vendor->id, 'kyc_status' => 'rejected']);
    }

    // ──────────────────────────────────────────────────────────
    // Approval Status
    // ──────────────────────────────────────────────────────────

    public function test_anyone_can_check_vendor_approval_status(): void
    {
        $vendor = Vendor::factory()->create(['kyc_status' => 'pending']);

        $this->getJson("/api/v1/vendors/{$vendor->id}/approval-status")
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
