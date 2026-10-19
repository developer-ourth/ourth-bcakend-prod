<?php

namespace Tests\Feature\Crm;

use App\Jobs\SendWhatsAppBroadcastMessage;
use App\Jobs\SyncLeadToKrayin;
use App\Jobs\SyncOrderToKrayin;
use App\Models\AttributionTouchpoint;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Services\KrayinClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrmLeadPipelineTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET = 'test-app-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.meta.app_secret' => self::APP_SECRET,
            'services.meta.verify_token' => 'verify-me',
            'services.meta.leads_access_token' => 'leads-token',
            'services.chatwoot.whatsapp_webhook_url' => 'https://chat.example.test/webhooks/whatsapp/+919000000000',
            'services.krayin.url' => 'https://crm.example.test',
            'services.krayin.api_email' => 'api@example.test',
            'services.krayin.api_password' => 'secret',
        ]);
    }

    // ── Access control ────────────────────────────────────────

    public function test_crm_admin_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/sales-leads')->assertStatus(401);
        $this->postJson('/api/v1/admin/whatsapp-broadcast', ['message' => 'hello world'])->assertStatus(401);
        $this->getJson('/api/v1/admin/marketing-attribution')->assertStatus(401);
    }

    public function test_crm_admin_endpoints_reject_consumers(): void
    {
        $consumer = User::factory()->create(['role' => 'consumer']);

        $this->actingAs($consumer)->getJson('/api/v1/admin/sales-leads')->assertStatus(403);
        $this->actingAs($consumer)->postJson('/api/v1/admin/whatsapp-broadcast', ['message' => 'hello world'])->assertStatus(403);
    }

    public function test_marketing_role_can_view_attribution(): void
    {
        $marketer = User::factory()->create(['role' => 'marketing']);

        $this->actingAs($marketer)->getJson('/api/v1/admin/marketing-attribution')
            ->assertOk()
            ->assertJsonPath('data.total_capi_synced', 0)
            ->assertJsonPath('data.crm_url', 'https://crm.example.test');
    }

    // ── Meta webhooks ─────────────────────────────────────────

    public function test_meta_webhooks_reject_invalid_signature(): void
    {
        $this->postJson('/api/v1/webhooks/meta-lead-form', ['entry' => []], ['X-Hub-Signature-256' => 'sha256=bad'])
            ->assertStatus(403);
        $this->postJson('/api/v1/webhooks/whatsapp', ['entry' => []])
            ->assertStatus(403);
    }

    public function test_meta_verification_handshake(): void
    {
        $this->get('/api/v1/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=42')
            ->assertOk()->assertSeeText('42');
        $this->get('/api/v1/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=42')
            ->assertStatus(403);
    }

    public function test_meta_lead_form_creates_lead_and_queues_crm_sync(): void
    {
        Queue::fake();
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => 'LG1',
                'field_data' => [
                    ['name' => 'full_name', 'values' => ['Asha Caterers']],
                    ['name' => 'phone_number', 'values' => ['+91 98765 43210']],
                    ['name' => 'email', 'values' => ['asha@example.com']],
                ],
            ]),
        ]);

        $payload = ['entry' => [['changes' => [['field' => 'leadgen', 'value' => [
            'leadgen_id' => 'LG1', 'form_id' => 'F1', 'ad_id' => 'AD1', 'campaign_id' => 'C1',
        ]]]]]];

        $this->postSigned('/api/v1/webhooks/meta-lead-form', $payload)->assertOk();
        // Meta redelivery of the same lead is ignored
        $this->postSigned('/api/v1/webhooks/meta-lead-form', $payload)->assertOk();

        $user = User::where('phone', '9876543210')->firstOrFail();
        $this->assertSame('B2B_Distributor', $user->crm_segment);
        $this->assertSame('asha@example.com', $user->email);
        $this->assertSame(1, AttributionTouchpoint::where('source_type', 'meta_lead_ad')->where('ad_id', 'AD1')->count());
        Queue::assertPushed(SyncLeadToKrayin::class, fn ($job) => $job->userId === $user->id && $job->sourceName === 'Meta Lead Ad');
        Queue::assertPushed(SyncLeadToKrayin::class, 1);
    }

    public function test_whatsapp_ad_click_is_attributed_and_forwarded_to_chatwoot(): void
    {
        Queue::fake();
        Http::fake(['chat.example.test/*' => Http::response('', 200)]);

        $existing = User::factory()->create(['phone' => '9876543210', 'role' => 'consumer']);

        $payload = ['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => [
            'contacts' => [['wa_id' => '919876543210', 'profile' => ['name' => 'Ravi']]],
            'messages' => [[
                'id' => 'wamid.1', 'from' => '919876543210', 'type' => 'text', 'text' => ['body' => 'Price?'],
                'referral' => ['source_type' => 'ad', 'source_id' => 'AD9', 'headline' => 'Diwali Sale', 'ctwa_clid' => 'CLID'],
            ]],
        ]]]]]];

        $this->postSigned('/api/v1/webhooks/whatsapp', $payload)->assertOk();
        $this->postSigned('/api/v1/webhooks/whatsapp', $payload)->assertOk();

        // Matched the existing user despite the +91 prefix, and recorded the ad once
        $this->assertSame(1, User::where('phone', 'like', '%9876543210')->count());
        $touchpoint = AttributionTouchpoint::where('source_type', 'whatsapp_ctwa')->sole();
        $this->assertSame($existing->id, $touchpoint->user_id);
        $this->assertSame('AD9', $touchpoint->ad_id);
        $this->assertSame('Diwali Sale', $touchpoint->campaign_name);

        $body = json_encode($payload);
        Http::assertSent(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://chat.example.test/webhooks/whatsapp/')
            && $request->body() === $body
            && $request->header('X-Hub-Signature-256')[0] === $this->sign($body));
    }

    public function test_whatsapp_webhook_asks_meta_to_retry_when_chatwoot_is_down(): void
    {
        Queue::fake();
        Http::fake(['chat.example.test/*' => Http::response('', 500)]);

        $this->postSigned('/api/v1/webhooks/whatsapp', ['entry' => []])->assertStatus(502);
    }

    public function test_first_message_from_unknown_number_becomes_a_lead(): void
    {
        Queue::fake();
        Http::fake(['chat.example.test/*' => Http::response('', 200)]);

        $payload = ['entry' => [['changes' => [['field' => 'messages', 'value' => [
            'contacts' => [['wa_id' => '919811111111', 'profile' => ['name' => 'New Person']]],
            'messages' => [['id' => 'wamid.2', 'from' => '919811111111', 'type' => 'text', 'text' => ['body' => 'Hi']]],
        ]]]]]];

        $this->postSigned('/api/v1/webhooks/whatsapp', $payload)->assertOk();

        $user = User::where('phone', '9811111111')->firstOrFail();
        $this->assertSame('New Person', $user->name);
        $this->assertSame('B2C', $user->crm_segment);
        Queue::assertPushed(SyncLeadToKrayin::class, fn ($job) => $job->sourceName === 'WhatsApp');
    }

    // ── Krayin sync jobs ──────────────────────────────────────

    public function test_lead_sync_creates_krayin_lead_then_adds_notes(): void
    {
        $this->fakeKrayin();
        $user = User::factory()->create(['phone' => '9876543210', 'crm_segment' => 'B2C']);

        (new SyncLeadToKrayin($user->id, 'Meta Lead Ad', 'Form answers'))->handle(app(KrayinClient::class));

        $user->refresh();
        $this->assertSame(501, $user->krayin_lead_id);
        $this->assertSame(77, $user->krayin_person_id);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api/v1/leads')
            && $r['person']['contact_numbers'][0]['value'] === '+919876543210'
            && $r['lead_source_id'] === 3);

        (new SyncLeadToKrayin($user->id, 'WhatsApp', 'Second enquiry'))->handle(app(KrayinClient::class));

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/api/v1/activities')
            && $r['type'] === 'note' && $r['lead_id'] === 501);
        Http::assertSentCount(7); // login, person search by phone + email, sources, types, create lead, note
    }

    public function test_paid_order_moves_lead_to_won(): void
    {
        $this->fakeKrayin();
        $user = User::factory()->create(['phone' => '9876543210', 'krayin_lead_id' => 501]);
        $order = $this->createOrder($user);

        (new SyncOrderToKrayin($order->id))->handle(app(KrayinClient::class));
        // A duplicate Razorpay webhook does nothing
        (new SyncOrderToKrayin($order->id))->handle(app(KrayinClient::class));

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/api/v1/leads/stage/edit/501')
            && $r['lead_pipeline_stage_id'] === 15);
        Http::assertSentCount(4); // login, pipelines, stage move, note
    }

    // ── Dashboards ────────────────────────────────────────────

    public function test_broadcast_is_queued_per_unique_recipient(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['phone' => '9000000001', 'crm_segment' => 'B2B_Distributor']);
        User::factory()->create(['phone' => '919000000001', 'crm_segment' => 'B2B_Distributor']); // same number
        User::factory()->create(['phone' => '9000000002', 'crm_segment' => 'B2C']);

        $this->actingAs($admin)->postJson('/api/v1/admin/whatsapp-broadcast', [
            'segment' => 'b2b',
            'message' => 'New catalogue is out',
            'template_name' => 'b2b_catalogue',
        ])->assertOk()->assertJsonPath('queued_count', 1);

        Queue::assertPushed(SendWhatsAppBroadcastMessage::class, 1);
        Queue::assertPushed(SendWhatsAppBroadcastMessage::class, fn ($job) => $job->phone === '9000000001' && $job->templateName === 'b2b_catalogue');
    }

    public function test_csv_import_skips_invalid_and_duplicate_numbers(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['phone' => '9000000003']);

        $csv = "Name,Phone,City,Segment\nGreen Caterers,+91 90000 00001,Pune,B2B_Distributor\nNo Phone,abc,Pune,B2C\nKnown,9000000003,Mumbai,B2C\n";

        $this->actingAs($admin)->post('/api/v1/admin/import-csv-leads', [
            'csv_file' => UploadedFile::fake()->createWithContent('leads.csv', $csv),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('imported_count', 1)
            ->assertJsonPath('duplicates_count', 1)
            ->assertJsonPath('invalid_count', 1);

        $this->assertDatabaseHas('users', ['phone' => '9000000001', 'name' => 'Green Caterers', 'crm_segment' => 'B2B_Distributor']);
    }

    public function test_sales_leads_list_and_segment_update(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = User::factory()->create(['phone' => '9000000001', 'crm_segment' => 'B2C', 'krayin_lead_id' => 9]);

        $this->actingAs($admin)->getJson('/api/v1/admin/sales-leads')
            ->assertOk()
            ->assertJsonPath('data.data.0.phone_number', '9000000001')
            ->assertJsonPath('data.data.0.crm_lead_url', 'https://crm.example.test/admin/leads/view/9');

        $this->actingAs($admin)->putJson("/api/v1/admin/sales-leads/{$lead->id}", ['segment' => 'B2B_Distributor'])->assertOk();
        $this->actingAs($admin)->putJson("/api/v1/admin/sales-leads/{$lead->id}", ['segment' => 'admin'])->assertStatus(422);
        $this->assertSame('B2B_Distributor', $lead->fresh()->crm_segment);
    }

    // ── Helpers ───────────────────────────────────────────────

    private function sign(string $body): string
    {
        return 'sha256=' . hash_hmac('sha256', $body, self::APP_SECRET);
    }

    private function postSigned(string $uri, array $payload)
    {
        $body = json_encode($payload);

        return $this->call('POST', $uri, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $this->sign($body),
        ], $body);
    }

    private function fakeKrayin(): void
    {
        Http::fake([
            'crm.example.test/api/v1/login' => Http::response(['token' => 'tkn']),
            'crm.example.test/api/v1/contacts/persons/search*' => Http::response(['data' => []]),
            'crm.example.test/api/v1/settings/sources*' => Http::response(['data' => [['id' => 3, 'name' => 'Meta Lead Ad']]]),
            'crm.example.test/api/v1/settings/types*' => Http::response(['data' => [['id' => 1, 'name' => 'New Business']]]),
            'crm.example.test/api/v1/settings/pipelines*' => Http::response(['data' => [[
                'id' => 1, 'is_default' => 1,
                'stages' => [['id' => 10, 'code' => 'new'], ['id' => 15, 'code' => 'won']],
            ]]]),
            'crm.example.test/api/v1/leads' => Http::response(['data' => ['data' => ['id' => 501, 'person' => ['id' => 77]]]]),
            'crm.example.test/api/v1/*' => Http::response(['data' => []]),
        ]);
    }

    private function createOrder(User $user): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'vendor_id' => Vendor::query()->value('id') ?? Vendor::factory()->create()->id,
            'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            'uuid' => Str::uuid(),
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
            'subtotal' => 500.00,
            'total_amount' => 500.00,
            'delivery_address_line1' => '12 Eco Street',
            'delivery_city' => 'Mumbai',
            'delivery_state' => 'Maharashtra',
            'delivery_postal_code' => '400001',
            'delivery_country' => 'India',
            'delivery_phone' => '9876543210',
        ]);
    }
}
