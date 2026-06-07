<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────
    // Listing
    // ──────────────────────────────────────────────────────────

    public function test_admin_can_list_products(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'user_type' => 'admin']);
        Product::factory()->count(3)->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/products')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_non_admin_cannot_access_admin_products(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);

        $this->actingAs($user)
            ->getJson('/api/v1/admin/products')
            ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_admin_products(): void
    {
        $this->getJson('/api/v1/admin/products')
            ->assertStatus(401);
    }

    // ──────────────────────────────────────────────────────────
    // Create
    // ──────────────────────────────────────────────────────────

    public function test_admin_can_create_a_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'user_type' => 'admin']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products', [
                'name'       => 'Organic Mango',
                'base_price' => 80.00,
                'category'   => 'food',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Organic Mango');

        $this->assertDatabaseHas('products', ['name' => 'Organic Mango']);
    }

    public function test_create_product_requires_name_and_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'user_type' => 'admin']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'base_price']);
    }

    public function test_create_product_rejects_duplicate_sku(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'user_type' => 'admin']);
        $existing = Product::factory()->create(['sku' => 'SKU-DUPE']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/products', [
                'name'       => 'Another Product',
                'base_price' => 50.00,
                'sku'        => $existing->sku,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_unauthenticated_cannot_create_product(): void
    {
        $this->postJson('/api/v1/admin/products', [
            'name'       => 'Hack',
            'base_price' => 1.00,
        ])->assertStatus(401);
    }

    // ──────────────────────────────────────────────────────────
    // Update
    // ──────────────────────────────────────────────────────────

    public function test_admin_can_update_a_product(): void
    {
        $admin   = User::factory()->create(['role' => 'admin', 'user_type' => 'admin']);
        $product = Product::factory()->create(['name' => 'Old Name', 'base_price' => 50.00]);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/products/{$product->id}", [
                'name'       => 'New Name',
                'base_price' => 60.00,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'New Name']);
    }

    public function test_non_admin_cannot_update_a_product(): void
    {
        $user    = User::factory()->create(['role' => 'consumer']);
        $product = Product::factory()->create();

        $this->actingAs($user)
            ->putJson("/api/v1/admin/products/{$product->id}", ['name' => 'Hacked'])
            ->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────
    // Delete
    // ──────────────────────────────────────────────────────────

    public function test_admin_can_delete_a_product_with_no_orders(): void
    {
        $admin   = User::factory()->create(['role' => 'admin', 'user_type' => 'admin']);
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('products', ['id' => $product->id, 'deleted_at' => null]);
    }

    public function test_non_admin_cannot_delete_a_product(): void
    {
        $user    = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create();

        $this->actingAs($user)
            ->deleteJson("/api/v1/admin/products/{$product->id}")
            ->assertStatus(403);
    }
}
