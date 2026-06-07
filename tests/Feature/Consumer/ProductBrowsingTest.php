<?php

namespace Tests\Feature\Consumer;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBrowsingTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────
    // Product listing (public)
    // ──────────────────────────────────────────────────────────

    public function test_anyone_can_list_active_products(): void
    {
        // Products from a verified vendor are visible
        $vendor = Vendor::factory()->create(['kyc_status' => 'verified']);
        Product::factory()->count(2)->create(['vendor_id' => $vendor->id, 'is_active' => true]);
        // Inactive product should be excluded
        Product::factory()->create(['vendor_id' => $vendor->id, 'is_active' => false]);

        $this->getJson('/api/v1/products')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_products_from_unverified_vendors_are_hidden(): void
    {
        $pendingVendor = Vendor::factory()->create(['kyc_status' => 'pending']);
        Product::factory()->count(2)->create(['vendor_id' => $pendingVendor->id, 'is_active' => true]);

        $this->getJson('/api/v1/products')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_product_list_can_be_filtered_by_search(): void
    {
        $vendor = Vendor::factory()->create(['kyc_status' => 'verified']);
        Product::factory()->create(['vendor_id' => $vendor->id, 'name' => 'Organic Apple', 'is_active' => true]);
        Product::factory()->create(['vendor_id' => $vendor->id, 'name' => 'Mango Pickle', 'is_active' => true]);

        $this->getJson('/api/v1/products?q=apple')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Organic Apple');
    }

    // ──────────────────────────────────────────────────────────
    // Product detail (public)
    // ──────────────────────────────────────────────────────────

    public function test_anyone_can_view_a_product_detail(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_returns_404_for_nonexistent_product(): void
    {
        $this->getJson('/api/v1/products/999999')
            ->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────
    // Vendor discovery (public)
    // ──────────────────────────────────────────────────────────

    public function test_vendor_list_only_returns_verified_vendors(): void
    {
        Vendor::factory()->create(['kyc_status' => 'verified']);
        Vendor::factory()->create(['kyc_status' => 'pending']);
        Vendor::factory()->create(['kyc_status' => 'rejected']);

        $this->getJson('/api/v1/vendors')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_vendor_detail_returns_404_for_unverified_vendor(): void
    {
        $vendor = Vendor::factory()->create(['kyc_status' => 'pending']);

        $this->getJson("/api/v1/vendors/{$vendor->id}")
            ->assertStatus(404);
    }

    public function test_vendor_detail_returns_verified_vendor_with_products(): void
    {
        $vendor = Vendor::factory()->create(['kyc_status' => 'verified']);
        Product::factory()->count(2)->create(['vendor_id' => $vendor->id, 'is_active' => true]);

        $this->getJson("/api/v1/vendors/{$vendor->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $vendor->id);
    }
}
