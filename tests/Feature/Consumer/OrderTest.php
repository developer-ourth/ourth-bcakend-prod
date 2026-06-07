<?php

namespace Tests\Feature\Consumer;

use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user   = User::factory()->create(['role' => 'consumer']);
        $this->token  = $this->user->createToken('mobile')->plainTextToken;
        $this->vendor = Vendor::factory()->create();
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    private function createOrder(User $user, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'user_id'                => $user->id,
            'vendor_id'              => $this->vendor->id,
            'order_number'           => 'ORD-' . strtoupper(Str::random(6)),
            'uuid'                   => Str::uuid(),
            'order_status'           => 'pending',
            'payment_status'         => 'pending',
            'subtotal'               => 200.00,
            'total_amount'           => 200.00,
            'delivery_address_line1' => '123 Test Street',
            'delivery_city'          => 'Mumbai',
            'delivery_state'         => 'Maharashtra',
            'delivery_postal_code'   => '400001',
            'delivery_country'       => 'India',
            'delivery_phone'         => '9999999999',
        ], $overrides));
    }

    // ──────────────────────────────────────────────────────────
    // List Orders
    // ──────────────────────────────────────────────────────────

    public function test_user_can_list_their_own_orders(): void
    {
        $this->createOrder($this->user);
        $this->createOrder($this->user);

        $response = $this->withToken($this->token)->getJson('/api/v1/me/orders');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_user_cannot_see_other_users_orders_in_listing(): void
    {
        $otherUser = User::factory()->create(['role' => 'consumer']);
        $this->createOrder($this->user);
        $this->createOrder($otherUser);

        $response = $this->withToken($this->token)->getJson('/api/v1/me/orders');

        // Must only return the authenticated user's order
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_unauthenticated_user_cannot_list_orders(): void
    {
        $this->getJson('/api/v1/me/orders')->assertStatus(401);
    }

    // ──────────────────────────────────────────────────────────
    // Show Order
    // ──────────────────────────────────────────────────────────

    public function test_user_can_view_their_own_order(): void
    {
        $order = $this->createOrder($this->user);

        $this->withToken($this->token)->getJson("/api/v1/me/orders/{$order->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_user_receives_403_when_viewing_another_users_order(): void
    {
        $otherUser  = User::factory()->create(['role' => 'consumer']);
        $otherOrder = $this->createOrder($otherUser);

        $this->withToken($this->token)->getJson("/api/v1/me/orders/{$otherOrder->id}")
            ->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────
    // Cancel Order
    // ──────────────────────────────────────────────────────────

    public function test_user_can_cancel_their_pending_order(): void
    {
        $order = $this->createOrder($this->user, ['order_status' => 'pending']);

        $this->withToken($this->token)->postJson("/api/v1/me/orders/{$order->id}/cancel", [
            'reason' => 'Changed my mind',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status' => 'cancelled']);
    }

    public function test_user_cannot_cancel_a_non_pending_order(): void
    {
        $order = $this->createOrder($this->user, ['order_status' => 'confirmed']);

        $this->withToken($this->token)->postJson("/api/v1/me/orders/{$order->id}/cancel")
            ->assertStatus(422);
    }

    public function test_user_receives_403_when_cancelling_another_users_order(): void
    {
        $otherUser  = User::factory()->create(['role' => 'consumer']);
        $otherOrder = $this->createOrder($otherUser, ['order_status' => 'pending']);

        $this->withToken($this->token)->postJson("/api/v1/me/orders/{$otherOrder->id}/cancel")
            ->assertStatus(403);
    }
}
