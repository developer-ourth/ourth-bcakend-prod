<?php

namespace Tests\Feature\Consumer;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    private Vendor $vendor;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Vendor user (buyer) — must have role 'vendor' for cart/order route middleware
        $this->user = User::factory()->create(['role' => 'vendor', 'user_type' => 'vendor']);
        $this->token = $this->user->createToken('mobile')->plainTextToken;
        Vendor::factory()->create(['user_id' => $this->user->id]);
        $this->user->refresh();

        // Distributor vendor — product seller
        $this->vendor = Vendor::factory()->create(['kyc_status' => 'verified']);
        $this->product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'base_price' => 100.00,
            'is_active' => true,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // Show Cart
    // ──────────────────────────────────────────────────────────

    public function test_empty_cart_returns_null_data(): void
    {
        $this->withToken($this->token)->getJson('/api/v1/me/cart')
            ->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    public function test_unauthenticated_user_cannot_access_cart(): void
    {
        $this->getJson('/api/v1/me/cart')->assertStatus(401);
    }

    // ──────────────────────────────────────────────────────────
    // Add Item
    // ──────────────────────────────────────────────────────────

    public function test_user_can_add_product_to_cart(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/me/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('carts', ['user_id' => $this->user->id, 'status' => 'active']);
        $this->assertDatabaseHas('cart_items', ['product_id' => $this->product->id, 'quantity' => 2]);
    }

    public function test_adding_same_product_increases_quantity(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/me/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $this->withToken($this->token)->postJson('/api/v1/me/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 3,
        ]);

        $this->assertDatabaseHas('cart_items', ['product_id' => $this->product->id, 'quantity' => 5]);
    }

    public function test_adding_product_from_different_vendor_updates_cart_vendor_id(): void
    {
        $otherVendor = Vendor::factory()->create(['kyc_status' => 'verified']);
        $otherProduct = Product::factory()->create(['vendor_id' => $otherVendor->id, 'is_active' => true]);

        $this->withToken($this->token)->postJson('/api/v1/me/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ])->assertStatus(200);

        // In the B2D model (single distributor), adding a product from a different vendor
        // silently updates the cart's vendor_id rather than blocking.
        $this->withToken($this->token)->postJson('/api/v1/me/cart/items', [
            'product_id' => $otherProduct->id,
            'quantity' => 1,
        ])->assertStatus(200);

        $this->assertDatabaseHas('carts', ['user_id' => $this->user->id, 'vendor_id' => $otherVendor->id]);
    }

    public function test_adding_product_without_vendor_returns_422(): void
    {
        $productWithoutVendor = Product::factory()->create([
            'vendor_id' => null,
            'is_active' => true,
        ]);

        $this->withToken($this->token)->postJson('/api/v1/me/cart/items', [
            'product_id' => $productWithoutVendor->id,
            'quantity' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This product is currently unavailable for checkout.');
    }

    // ──────────────────────────────────────────────────────────
    // Update Item
    // ──────────────────────────────────────────────────────────

    public function test_user_can_update_cart_item_quantity(): void
    {
        $cart = Cart::create(['user_id' => $this->user->id, 'vendor_id' => $this->vendor->id, 'status' => 'active']);
        $item = CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100, 'total_price' => 200]);

        $this->withToken($this->token)->patchJson("/api/v1/me/cart/items/{$item->id}", ['quantity' => 5])
            ->assertStatus(200);

        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 5]);
    }

    public function test_setting_quantity_to_zero_removes_item(): void
    {
        $cart = Cart::create(['user_id' => $this->user->id, 'vendor_id' => $this->vendor->id, 'status' => 'active']);
        $item = CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100, 'total_price' => 200]);

        $this->withToken($this->token)->patchJson("/api/v1/me/cart/items/{$item->id}", ['quantity' => 0])
            ->assertStatus(200);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_user_cannot_update_another_users_cart_item(): void
    {
        $otherUser = User::factory()->create(['role' => 'consumer']);
        $cart = Cart::create(['user_id' => $otherUser->id, 'vendor_id' => $this->vendor->id, 'status' => 'active']);
        $item = CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100, 'total_price' => 100]);

        $this->withToken($this->token)->patchJson("/api/v1/me/cart/items/{$item->id}", ['quantity' => 10])
            ->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────
    // Remove Item
    // ──────────────────────────────────────────────────────────

    public function test_user_can_remove_cart_item(): void
    {
        $cart = Cart::create(['user_id' => $this->user->id, 'vendor_id' => $this->vendor->id, 'status' => 'active']);
        $item = CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100, 'total_price' => 100]);

        $this->withToken($this->token)->deleteJson("/api/v1/me/cart/items/{$item->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    // ──────────────────────────────────────────────────────────
    // Clear Cart
    // ──────────────────────────────────────────────────────────

    public function test_user_can_clear_their_cart(): void
    {
        Cart::create(['user_id' => $this->user->id, 'vendor_id' => $this->vendor->id, 'status' => 'active']);

        $this->withToken($this->token)->deleteJson('/api/v1/me/cart')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('carts', ['user_id' => $this->user->id, 'status' => 'active']);
    }
}
