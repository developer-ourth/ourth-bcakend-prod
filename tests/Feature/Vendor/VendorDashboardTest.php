<?php

namespace Tests\Feature\Vendor;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorDashboardTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────
    // Vendor dashboard overview
    // ──────────────────────────────────────────────────────────

    public function test_vendor_can_view_own_dashboard(): void
    {
        $vendor = Vendor::factory()->create();
        $user   = $vendor->user;
        $user->update(['role' => 'vendor', 'user_type' => 'vendor']);

        $this->actingAs($user)
            ->getJson("/api/v1/dashboard/vendor/{$vendor->id}")
            ->assertStatus(200)
            ->assertJsonPath('vendor.id', $vendor->id)
            ->assertJsonStructure([
                'vendor'        => ['id', 'business_name', 'kyc_status'],
                'today'         => ['orders', 'revenue'],
                'this_week'     => ['orders', 'revenue'],
                'pending_orders',
                'recent_orders',
            ]);
    }

    public function test_admin_can_view_any_vendor_dashboard(): void
    {
        $admin  = User::factory()->create(['role' => 'admin', 'user_type' => 'admin']);
        $vendor = Vendor::factory()->create();

        $this->actingAs($admin)
            ->getJson("/api/v1/dashboard/vendor/{$vendor->id}")
            ->assertStatus(200)
            ->assertJsonPath('vendor.id', $vendor->id);
    }

    public function test_consumer_cannot_access_vendor_dashboard(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);
        $vendor   = Vendor::factory()->create();

        $this->actingAs($consumer)
            ->getJson("/api/v1/dashboard/vendor/{$vendor->id}")
            ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_vendor_dashboard(): void
    {
        $vendor = Vendor::factory()->create();

        $this->getJson("/api/v1/dashboard/vendor/{$vendor->id}")
            ->assertStatus(401);
    }

    // ──────────────────────────────────────────────────────────
    // Catalog endpoint
    // ──────────────────────────────────────────────────────────

    public function test_vendor_can_view_own_catalog(): void
    {
        $vendor = Vendor::factory()->create();
        $user   = $vendor->user;
        $user->update(['role' => 'vendor', 'user_type' => 'vendor']);

        $this->actingAs($user)
            ->getJson("/api/v1/dashboard/vendor/{$vendor->id}/catalog")
            ->assertStatus(200)
            ->assertJsonStructure(['vendor_id', 'products']);
    }
}
