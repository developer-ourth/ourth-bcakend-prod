<?php

namespace Tests\Feature\Consumer;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderPlacementTest extends TestCase
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
            'is_active' => true,
            'base_price' => 80,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    private function addProductToCart(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/me/cart/items', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);
    }

    /** @return array<string, mixed> */
    private function checkoutPayload(array $overrides = []): array
    {
        return array_merge([
            'delivery_address_line1' => '12 Eco Street',
            'delivery_city' => 'Mumbai',
            'delivery_state' => 'Maharashtra',
            'delivery_postal_code' => '400001',
            'delivery_phone' => '9876543210',
            'payment_method' => 'cod',
        ], $overrides);
    }

    private function createOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $this->user->id,
            'vendor_id' => $this->vendor->id,
            'order_number' => 'ORD-'.strtoupper(Str::random(8)),
            'uuid' => Str::uuid(),
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'subtotal' => 160.00,
            'total_amount' => 160.00,
            'delivery_address_line1' => '12 Eco Street',
            'delivery_city' => 'Mumbai',
            'delivery_state' => 'Maharashtra',
            'delivery_postal_code' => '400001',
            'delivery_country' => 'India',
            'delivery_phone' => '9876543210',
        ], $overrides));
    }

    // ──────────────────────────────────────────────────────────
    // Order Placement (POST /api/v1/me/orders)
    // ──────────────────────────────────────────────────────────

    public function test_consumer_can_place_order_from_cart(): void
    {
        $this->addProductToCart();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/me/orders', $this->checkoutPayload());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Order placed successfully.');

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'vendor_id' => $this->vendor->id,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'delivery_city' => 'Mumbai',
        ]);

        // Cart should be converted
        $this->assertDatabaseHas('carts', [
            'user_id' => $this->user->id,
            'status' => 'converted_to_order',
        ]);

        // Payment record should be created
        $order = Order::where('user_id', $this->user->id)->first();
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'cod',
            'status' => 'pending',
        ]);
    }

    public function test_order_total_reflects_cart_items(): void
    {
        $this->addProductToCart(); // 2 × 80 = 160

        $this->withToken($this->token)
            ->postJson('/api/v1/me/orders', $this->checkoutPayload())
            ->assertStatus(201);

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'total_amount' => 160.00,
        ]);
    }

    public function test_place_order_with_empty_cart_returns_422(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/me/orders', $this->checkoutPayload())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Your cart is empty.');
    }

    public function test_unauthenticated_user_cannot_place_order(): void
    {
        $this->postJson('/api/v1/me/orders', $this->checkoutPayload())
            ->assertStatus(401);
    }

    public function test_place_order_requires_delivery_address_fields(): void
    {
        $this->addProductToCart();

        $this->withToken($this->token)
            ->postJson('/api/v1/me/orders', [
                'payment_method' => 'cod',
                // missing all delivery fields
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'delivery_address_line1',
                'delivery_city',
                'delivery_state',
                'delivery_postal_code',
                'delivery_phone',
            ]);
    }

    public function test_place_order_requires_valid_payment_method(): void
    {
        $this->addProductToCart();

        $this->withToken($this->token)
            ->postJson('/api/v1/me/orders', $this->checkoutPayload(['payment_method' => 'bitcoin']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    // ──────────────────────────────────────────────────────────
    // Razorpay Initiation (POST /api/v1/me/orders/{order}/payments/razorpay/initiate)
    // ──────────────────────────────────────────────────────────

    public function test_consumer_can_initiate_razorpay_payment(): void
    {
        Config::set('services.razorpay.key', 'rzp_test_key');
        Config::set('services.razorpay.secret', 'rzp_test_secret');

        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_testXYZ123',
                'amount' => 16000,
                'currency' => 'INR',
                'status' => 'created',
            ], 200),
        ]);

        $order = $this->createOrder();

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/me/orders/{$order->id}/payments/razorpay/initiate");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.razorpay_order_id', 'order_testXYZ123')
            ->assertJsonPath('data.key', 'rzp_test_key');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_gateway' => 'razorpay',
            'transaction_id' => 'order_testXYZ123',
        ]);
    }

    public function test_initiate_payment_fails_when_gateway_not_configured(): void
    {
        Config::set('services.razorpay.key', '');
        Config::set('services.razorpay.secret', '');

        $order = $this->createOrder();

        $this->withToken($this->token)
            ->postJson("/api/v1/me/orders/{$order->id}/payments/razorpay/initiate")
            ->assertStatus(500)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_initiate_payment_for_already_paid_order(): void
    {
        Config::set('services.razorpay.key', 'rzp_test_key');
        Config::set('services.razorpay.secret', 'rzp_test_secret');

        $order = $this->createOrder(['payment_status' => 'paid']);

        $this->withToken($this->token)
            ->postJson("/api/v1/me/orders/{$order->id}/payments/razorpay/initiate")
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This order is already paid.');
    }

    public function test_cannot_initiate_payment_for_cancelled_order(): void
    {
        Config::set('services.razorpay.key', 'rzp_test_key');
        Config::set('services.razorpay.secret', 'rzp_test_secret');

        $order = $this->createOrder(['order_status' => 'cancelled']);

        $this->withToken($this->token)
            ->postJson("/api/v1/me/orders/{$order->id}/payments/razorpay/initiate")
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot initiate payment for a cancelled order.');
    }

    public function test_cannot_initiate_payment_for_another_users_order(): void
    {
        Config::set('services.razorpay.key', 'rzp_test_key');
        Config::set('services.razorpay.secret', 'rzp_test_secret');

        $otherUser = User::factory()->create(['role' => 'consumer']);
        $order = Order::create([
            'user_id' => $otherUser->id,
            'vendor_id' => $this->vendor->id,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'delivery_address_line1' => '99 Other Street',
            'delivery_city' => 'Delhi',
            'delivery_state' => 'Delhi',
            'delivery_postal_code' => '110001',
            'delivery_country' => 'India',
            'delivery_phone' => '9000000000',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/me/orders/{$order->id}/payments/razorpay/initiate")
            ->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────
    // Razorpay Verification (POST /api/v1/me/orders/{order}/payments/razorpay/verify)
    // ──────────────────────────────────────────────────────────

    public function test_payment_verification_succeeds_with_valid_signature(): void
    {
        $secret = 'rzp_test_secret';
        Config::set('services.razorpay.secret', $secret);

        $order = $this->createOrder();
        $razorpayOrderId = 'order_testXYZ123';
        $razorpayPaymentId = 'pay_testABC456';
        $signature = hash_hmac('sha256', "{$razorpayOrderId}|{$razorpayPaymentId}", $secret);

        $this->withToken($this->token)
            ->postJson("/api/v1/me/orders/{$order->id}/payments/razorpay/verify", [
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId,
                'razorpay_signature' => $signature,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Payment verified successfully.');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_payment_verification_fails_with_invalid_signature(): void
    {
        Config::set('services.razorpay.secret', 'rzp_test_secret');

        $order = $this->createOrder();

        $this->withToken($this->token)
            ->postJson("/api/v1/me/orders/{$order->id}/payments/razorpay/verify", [
                'razorpay_order_id' => 'order_testXYZ123',
                'razorpay_payment_id' => 'pay_testABC456',
                'razorpay_signature' => 'completely_wrong_signature',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Payment verification failed.');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'failed',
        ]);
    }

    public function test_payment_verification_fails_without_secret_configured(): void
    {
        Config::set('services.razorpay.secret', '');

        $order = $this->createOrder();

        $this->withToken($this->token)
            ->postJson("/api/v1/me/orders/{$order->id}/payments/razorpay/verify", [
                'razorpay_order_id' => 'order_testXYZ123',
                'razorpay_payment_id' => 'pay_testABC456',
                'razorpay_signature' => 'any_signature',
            ])
            ->assertStatus(500)
            ->assertJsonPath('success', false);
    }
}
