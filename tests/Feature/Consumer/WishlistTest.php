<?php

namespace Tests\Feature\Consumer;

use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user    = User::factory()->create(['role' => 'consumer']);
        $this->token   = $this->user->createToken('mobile')->plainTextToken;
        $vendor        = Vendor::factory()->create();
        $this->product = Product::factory()->create(['vendor_id' => $vendor->id]);
    }

    public function test_unauthenticated_cannot_access_wishlist(): void
    {
        $this->getJson('/api/v1/me/wishlist')->assertUnauthorized();
    }

    public function test_wishlist_is_empty_by_default(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/me/wishlist')
            ->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_user_can_add_product_to_wishlist(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/me/wishlist', ['product_id' => $this->product->id])
            ->assertCreated();
    }

    public function test_adding_duplicate_is_idempotent(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/me/wishlist', ['product_id' => $this->product->id])
            ->assertCreated();

        $this->withToken($this->token)
            ->postJson('/api/v1/me/wishlist', ['product_id' => $this->product->id])
            ->assertCreated();

        $this->assertDatabaseCount('wishlists', 1);
    }

    public function test_wishlist_shows_added_product(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/me/wishlist', ['product_id' => $this->product->id]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/me/wishlist')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->product->id, $response->json('data.0.id'));
    }

    public function test_user_can_remove_product_from_wishlist(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/me/wishlist', ['product_id' => $this->product->id]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/me/wishlist/{$this->product->id}")
            ->assertNoContent();

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_remove_nonexistent_wishlist_item_returns_not_found(): void
    {
        $this->withToken($this->token)
            ->deleteJson("/api/v1/me/wishlist/{$this->product->id}")
            ->assertNotFound();
    }
}
