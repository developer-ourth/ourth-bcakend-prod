<?php

namespace Tests\Feature\Zoho;

use App\Jobs\PushOrderToZoho;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductPack;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Zoho\ZohoInventoryClient;
use App\Services\Zoho\ZohoOrderSync;
use App\Services\Zoho\ZohoStockSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ZohoInventoryTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://zoho.test/inventory/v1';

    /** @var array<string, array|callable> "METHOD path" => response body */
    private array $routes = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.zoho.client_id' => 'cid',
            'services.zoho.client_secret' => 'secret',
            'services.zoho.refresh_token' => 'refresh',
            'services.zoho.organization_id' => '60094465589',
            'services.zoho.accounts_url' => 'https://accounts.zoho.test',
            'services.zoho.api_url' => self::API,
            'services.zoho.prices_include_tax' => true,
            'services.zoho.payment_mode' => 'others',
            'services.zoho.orders_from' => null,
        ]);

        Http::fake(function (HttpRequest $request) {
            if (str_starts_with($request->url(), 'https://accounts.zoho.test/oauth/v2/token')) {
                return Http::response(['access_token' => 'tok', 'expires_in' => 3600]);
            }

            $path = trim(str_replace(self::API, '', strtok($request->url(), '?')), '/');
            $handler = $this->routes["{$request->method()} {$path}"] ?? null;

            if ($handler === null) {
                return Http::response(['code' => 1002, 'message' => "No fake for {$request->method()} {$path}"], 404);
            }

            $body = is_callable($handler) ? $handler($request) : $handler;

            return $body instanceof \GuzzleHttp\Promise\PromiseInterface ? $body : Http::response($body + ['code' => 0]);
        });
    }

    // ── Stock: Zoho → website ─────────────────────────────────

    public function test_stock_sync_matches_by_sku_and_holds_back_unsynced_orders(): void
    {
        $vendor = Vendor::factory()->create();
        $plate = Product::factory()->create(['vendor_id' => $vendor->id, 'sku' => 'PLATE-10']);
        $bowlProduct = Product::factory()->create(['vendor_id' => $vendor->id, 'sku' => 'BOWL']);
        $pack = ProductPack::create(['product_id' => $bowlProduct->id, 'name' => 'Pack of 50', 'base_price' => 300, 'sku' => 'bowl-50', 'stock_quantity' => 999, 'is_active' => true]);
        $unmatched = Product::factory()->create(['vendor_id' => $vendor->id, 'sku' => 'NOT-IN-ZOHO']);

        // A customer is paying for 3 plates and 2 bowl packs right now: Zoho doesn't know yet
        $this->makeOrder($plate, null, 3, ['payment_status' => 'pending']);
        $this->makeOrder($bowlProduct, $pack, 2, ['payment_status' => 'pending']);
        // Already in Zoho (Zoho's stock already reflects it) and a cancelled order: both ignored
        $this->makeOrder($plate, null, 5, ['payment_status' => 'paid', 'zoho_salesorder_id' => 'SO-OLD']);
        $this->makeOrder($plate, null, 7, ['order_status' => 'cancelled']);

        $this->routes['GET items'] = ['items' => [
            ['item_id' => '111', 'sku' => 'PLATE-10', 'name' => 'Plate', 'available_for_sale_stock' => 40, 'stock_on_hand' => 45],
            ['item_id' => '222', 'sku' => 'BOWL-50', 'name' => 'Bowl 50', 'available_for_sale_stock' => 10],
            ['item_id' => '333', 'sku' => '', 'name' => 'No SKU'],
        ], 'page_context' => ['has_more_page' => false]];

        $status = app(ZohoStockSync::class)->run();

        $this->assertSame('available_for_sale_stock', $status['stock_field']);
        $this->assertSame(1, $status['packs_synced']);
        $this->assertSame(1, $status['unmatched_count']);
        $this->assertSame('NOT-IN-ZOHO', $status['unmatched'][0]['sku']);

        $this->assertSame(8, $pack->fresh()->stock_quantity);          // 10 − 2 pending
        $this->assertSame('222', $pack->fresh()->zoho_item_id);

        $inventory = Inventory::where('product_id', $plate->id)->sole();
        $this->assertSame(40, $inventory->current_stock);
        $this->assertSame(3, $inventory->reserved_stock);
        $this->assertSame(37, $inventory->fresh()->available_stock);   // what checkout checks
        $this->assertSame('111', $plate->fresh()->zoho_item_id);
        $this->assertNull($unmatched->fresh()->zoho_item_id);

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'organization_id=60094465589')
            && $r->header('Authorization')[0] === 'Zoho-oauthtoken tok');
    }

    public function test_expired_token_is_refreshed_once_and_request_retried(): void
    {
        $calls = 0;
        $this->routes['GET items'] = function () use (&$calls) {
            return ++$calls === 1
                ? Http::response(['code' => 57, 'message' => 'You are not authorized'], 401)
                : ['items' => [], 'page_context' => ['has_more_page' => false]];
        };

        app(ZohoInventoryClient::class)->get('items');

        $this->assertSame(2, $calls);
        Http::assertSentCount(4); // token, items(401), token, items
    }

    // ── Orders: website → Zoho ────────────────────────────────

    public function test_paid_order_becomes_contact_sales_order_invoice_and_payment(): void
    {
        [$order, $pack] = $this->paidPackOrder();
        $this->fakeFullOrderFlow();

        app(ZohoOrderSync::class)->push($order);

        $order->refresh();
        $this->assertSame('SO1', $order->zoho_salesorder_id);
        $this->assertSame('INV1', $order->zoho_invoice_id);
        $this->assertSame('PAY1', $order->zoho_payment_id);
        $this->assertNotNull($order->zoho_synced_at);
        $this->assertNull($order->zoho_sync_error);
        $this->assertSame('C1', $order->user->fresh()->zoho_contact_id);

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_ends_with(strtok($r->url(), '?'), '/contacts')
            && $r['place_of_contact'] === 'MH' && $r['gst_treatment'] === 'consumer' && $r['billing_address']['zip'] === '400001');
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_ends_with(strtok($r->url(), '?'), '/salesorders')
            && $r['customer_id'] === 'C1' && $r['reference_number'] === $order->order_number
            && $r['line_items'][0]['item_id'] === '222' && $r['line_items'][0]['quantity'] === 2
            && $r['is_inclusive_tax'] === true && $r['place_of_supply'] === 'MH' && $r['shipping_charge'] === 40.0);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/salesorders/SO1/status/confirmed'));
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_ends_with(strtok($r->url(), '?'), '/invoices')
            && $r['line_items'][0]['salesorder_item_id'] === 'L1');
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/invoices/INV1/status/sent'));
        Http::assertSent(fn (HttpRequest $r) => str_ends_with(strtok($r->url(), '?'), '/customerpayments')
            && $r['amount'] === 640.0 && $r['reference_number'] === 'pay_ABC'
            && $r['invoices'][0] === ['invoice_id' => 'INV1', 'amount_applied' => 640.0]);
    }

    public function test_retry_after_partial_sync_creates_nothing_twice(): void
    {
        [$order] = $this->paidPackOrder();
        $order->user->forceFill(['zoho_contact_id' => 'C1'])->save();
        $order->forceFill(['zoho_salesorder_id' => 'SO1', 'zoho_invoice_id' => 'INV1'])->save();

        $this->fakeFullOrderFlow(invoiceBalance: 0, salesOrderStatus: 'confirmed'); // payment was recorded before the crash

        app(ZohoOrderSync::class)->push($order->fresh());

        // Only reads (plus the OAuth token): nothing is created again in Zoho
        Http::assertNotSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_starts_with($r->url(), self::API));
        $this->assertNotNull($order->fresh()->zoho_synced_at);
    }

    public function test_existing_sales_order_is_found_by_order_number(): void
    {
        [$order] = $this->paidPackOrder();
        $this->fakeFullOrderFlow();
        $this->routes['GET salesorders'] = ['salesorders' => [['salesorder_id' => 'SO1', 'reference_number' => $order->order_number]]];

        app(ZohoOrderSync::class)->push($order);

        Http::assertNotSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_ends_with(strtok($r->url(), '?'), '/salesorders'));
        $this->assertSame('SO1', $order->fresh()->zoho_salesorder_id);
    }

    public function test_missing_sku_fails_with_clear_message_without_endless_retries(): void
    {
        [$order, $pack] = $this->paidPackOrder();
        $pack->forceFill(['zoho_item_id' => null, 'sku' => 'GHOST-1'])->save();
        $this->fakeFullOrderFlow();
        $this->routes['GET items'] = ['items' => []];

        (new PushOrderToZoho($order->id))->withFakeQueueInteractions()->handle(app(ZohoInventoryClient::class), app(ZohoOrderSync::class));

        $this->assertStringContainsString('No Zoho item with SKU "GHOST-1"', $order->fresh()->zoho_sync_error);
        $this->assertNull($order->fresh()->zoho_salesorder_id);
    }

    public function test_rejected_place_of_supply_is_retried_without_it_and_flagged(): void
    {
        [$order] = $this->paidPackOrder();
        $this->fakeFullOrderFlow();
        $this->routes['POST salesorders'] = fn (HttpRequest $r) => isset($r['place_of_supply'])
            ? Http::response(['code' => 4, 'message' => 'Invalid value passed for place_of_supply'], 400)
            : ['salesorder' => ['salesorder_id' => 'SO1']];

        app(ZohoOrderSync::class)->push($order);

        $this->assertSame('SO1', $order->fresh()->zoho_salesorder_id);
        $this->assertStringStartsWith('WARNING:', $order->fresh()->zoho_sync_error);
    }

    public function test_only_paid_orders_after_go_live_date_are_synced(): void
    {
        [$order] = $this->paidPackOrder();
        $this->assertTrue(ZohoOrderSync::shouldSync($order));

        config(['services.zoho.orders_from' => now()->addDay()->toDateString()]);
        $this->assertFalse(ZohoOrderSync::shouldSync($order));

        config(['services.zoho.orders_from' => null]);
        $order->payment_status = 'pending';
        $this->assertFalse(ZohoOrderSync::shouldSync($order));
    }

    // ── Admin endpoints ───────────────────────────────────────

    public function test_admin_zoho_endpoints_are_protected_and_report_status(): void
    {
        Queue::fake();
        $this->getJson('/api/v1/admin/zoho/status')->assertStatus(401);
        $this->actingAs(User::factory()->create(['role' => 'consumer']))->getJson('/api/v1/admin/zoho/status')->assertStatus(403);

        [$order] = $this->paidPackOrder();
        $order->forceFill(['created_at' => now()->subHour(), 'zoho_sync_error' => 'No Zoho item with SKU "X"'])->save();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/v1/admin/zoho/status')
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.orders_needing_attention.0.order_number', $order->order_number);

        $this->actingAs($admin)->postJson("/api/v1/admin/zoho/orders/{$order->id}/retry")->assertOk();
        Queue::assertPushed(PushOrderToZoho::class, fn ($job) => $job->orderId === $order->id && $job->force);

        $this->actingAs($admin)->postJson('/api/v1/admin/zoho/sync-stock')->assertOk();
    }

    // ── Helpers ───────────────────────────────────────────────

    private function makeOrder(Product $product, ?ProductPack $pack, int $qty, array $attributes = []): Order
    {
        $user = User::factory()->create(['phone' => (string) random_int(7000000000, 9999999999)]);

        $order = Order::create(array_merge([
            'user_id' => $user->id,
            'vendor_id' => $product->vendor_id,
            'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            'uuid' => Str::uuid(),
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'subtotal' => 600,
            'delivery_charge' => 40,
            'total_amount' => 640,
            'delivery_address_line1' => '12 Eco Street',
            'delivery_city' => 'Mumbai',
            'delivery_state' => 'Maharashtra',
            'delivery_postal_code' => '400001',
            'delivery_country' => 'India',
            'delivery_phone' => $user->phone,
        ], array_diff_key($attributes, ['zoho_salesorder_id' => 1])));

        if (isset($attributes['zoho_salesorder_id'])) {
            $order->forceFill(['zoho_salesorder_id' => $attributes['zoho_salesorder_id']])->save();
        }

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_pack_id' => $pack?->id,
            'product_name' => $product->name,
            'product_sku' => $pack?->sku ?? $product->sku,
            'quantity' => $qty,
            'unit_price' => 300,
            'total_price' => 300 * $qty,
        ]);

        return $order;
    }

    /** @return array{0: Order, 1: ProductPack} */
    private function paidPackOrder(): array
    {
        $product = Product::factory()->create(['sku' => 'BOWL']);
        $pack = ProductPack::create(['product_id' => $product->id, 'name' => 'Pack of 50', 'base_price' => 300, 'sku' => 'BOWL-50', 'stock_quantity' => 10, 'is_active' => true]);
        $pack->forceFill(['zoho_item_id' => '222'])->save();

        $order = $this->makeOrder($product, $pack, 2);
        Payment::create(['order_id' => $order->id, 'payment_gateway' => 'razorpay', 'payment_method' => 'upi', 'transaction_id' => 'pay_ABC', 'amount' => 640, 'status' => 'completed', 'paid_at' => now()]);

        return [$order->fresh(), $pack];
    }

    private function fakeFullOrderFlow(float $invoiceBalance = 640, string $salesOrderStatus = 'draft'): void
    {
        $this->routes += [
            'GET contacts' => ['contacts' => []],
            'POST contacts' => ['contact' => ['contact_id' => 'C1']],
            'GET salesorders' => ['salesorders' => []],
            'POST salesorders' => ['salesorder' => ['salesorder_id' => 'SO1']],
            'GET salesorders/SO1' => ['salesorder' => ['salesorder_id' => 'SO1', 'status' => $salesOrderStatus, 'place_of_supply' => 'MH',
                'line_items' => [['line_item_id' => 'L1', 'item_id' => '222', 'quantity' => 2, 'rate' => 300]]]],
            'POST salesorders/SO1/status/confirmed' => ['message' => 'Confirmed'],
            'GET invoices' => ['invoices' => []],
            'POST invoices' => ['invoice' => ['invoice_id' => 'INV1']],
            'POST invoices/INV1/status/sent' => ['message' => 'Sent'],
            'GET invoices/INV1' => ['invoice' => ['invoice_id' => 'INV1', 'total' => 640, 'balance' => $invoiceBalance]],
            'POST customerpayments' => ['payment' => ['payment_id' => 'PAY1']],
        ];
    }
}
