<?php

namespace App\Services\Zoho;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPack;
use App\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends a paid website order to Zoho Inventory as:
 *   customer (contact) → confirmed sales order → invoice (marked sent) → customer payment.
 *
 * Each Zoho id is saved on the order as soon as it exists, and existing records are
 * looked up by the website order number first, so a retry never creates duplicates.
 */
class ZohoOrderSync
{
    /** Warnings that don't stop the sync but someone should look at. */
    private array $warnings = [];

    public function __construct(private ZohoInventoryClient $zoho) {}

    public static function shouldSync(Order $order): bool
    {
        if ($order->payment_status !== 'paid' || $order->order_status === 'cancelled') {
            return false;
        }

        $from = config('services.zoho.orders_from');

        return ! $from || $order->created_at->gte(Carbon::parse($from)->startOfDay());
    }

    public function push(Order $order): void
    {
        $this->warnings = [];
        $order->loadMissing('items.product', 'items.productPack', 'user', 'payment');

        $contactId = $this->ensureContact($order);
        $salesOrder = $this->ensureSalesOrder($order, $contactId);
        $invoice = $this->ensureInvoice($order, $contactId, $salesOrder);
        $this->ensurePayment($order, $contactId, $invoice);

        $order->forceFill([
            'zoho_synced_at' => now(),
            'zoho_sync_error' => $this->warnings ? 'WARNING: ' . implode(' ', $this->warnings) : null,
        ])->save();
    }

    // ── Contact ─────────────────────────────────────────────────────────

    private function ensureContact(Order $order): string
    {
        $user = $order->user;

        if ($user?->zoho_contact_id) {
            return $user->zoho_contact_id;
        }

        $phone = PhoneNumber::normalize($order->delivery_phone ?: $user?->phone);
        $email = filter_var($user?->email, FILTER_VALIDATE_EMAIL) ? $user->email : null;

        $contactId = $this->findContact($phone, $email) ?? $this->createContact($order, $phone, $email);

        $user?->forceFill(['zoho_contact_id' => $contactId])->save();

        return $contactId;
    }

    private function findContact(?string $phone, ?string $email): ?string
    {
        $searches = array_filter([
            $phone ? ['phone_contains' => substr($phone, -10)] : null,
            $email ? ['email' => $email] : null,
        ]);

        foreach ($searches as $query) {
            $contacts = $this->zoho->get('contacts', $query)['contacts'] ?? [];

            if (! empty($contacts[0]['contact_id'])) {
                return (string) $contacts[0]['contact_id'];
            }
        }

        return null;
    }

    private function createContact(Order $order, ?string $phone, ?string $email): string
    {
        $name = trim($order->user?->name ?: 'Customer ' . substr((string) $phone, -4));
        $address = $this->address($order);
        $gst = $this->gstFields($order);

        $payload = array_filter([
            'contact_name' => $name,
            'contact_type' => 'customer',
            'gst_treatment' => $gst['gst_treatment'],
            'gst_no' => $gst['gst_no'],
            'place_of_contact' => $gst['place'],
            'billing_address' => $address,
            'shipping_address' => $address,
            'contact_persons' => [array_filter([
                'first_name' => $name,
                'email' => $email,
                'mobile' => $phone,
                'is_primary_contact' => true,
            ])],
        ]);

        $response = $this->withPlaceFallback($payload, ['place_of_contact'], fn ($body) => $this->zoho->post('contacts', $body));

        return (string) $response['contact']['contact_id'];
    }

    // ── Sales order ─────────────────────────────────────────────────────

    private function ensureSalesOrder(Order $order, string $contactId): array
    {
        $id = $order->zoho_salesorder_id ?? $this->findByReference('salesorders', 'salesorders', 'salesorder_id', $order->order_number);

        if (! $id) {
            $gst = $this->gstFields($order);

            $payload = array_filter([
                'customer_id' => $contactId,
                'reference_number' => $order->order_number,
                'date' => $order->created_at->toDateString(),
                'line_items' => $this->lineItems($order),
                'is_inclusive_tax' => (bool) config('services.zoho.prices_include_tax'),
                'gst_treatment' => $gst['gst_treatment'],
                'gst_no' => $gst['gst_no'],
                'place_of_supply' => $gst['place'],
                'shipping_charge' => (float) $order->delivery_charge ?: null,
                'notes' => "Website order {$order->order_number}",
            ] + $this->discountFields($order), fn ($v) => $v !== null && $v !== '');

            $response = $this->withPlaceFallback($payload, ['place_of_supply'], fn ($body) => $this->zoho->post('salesorders', $body));
            $id = (string) $response['salesorder']['salesorder_id'];
        }

        $order->forceFill(['zoho_salesorder_id' => $id])->save();

        $salesOrder = $this->zoho->get("salesorders/{$id}")['salesorder'];

        // Confirming commits the stock in Zoho
        if (($salesOrder['status'] ?? '') === 'draft') {
            $this->zoho->post("salesorders/{$id}/status/confirmed");
        }

        return $salesOrder;
    }

    private function lineItems(Order $order): array
    {
        return $order->items->map(fn ($item) => [
            'item_id' => $this->zohoItemId($item->productPack ?? $item->product, $item->product_sku),
            'quantity' => (int) $item->quantity,
            'rate' => (float) $item->unit_price,
        ])->all();
    }

    private function zohoItemId(Product|ProductPack|null $model, ?string $sku): string
    {
        if ($model?->zoho_item_id) {
            return $model->zoho_item_id;
        }

        $sku = ZohoStockSync::normalizeSku($model?->sku ?? $sku);

        if ($sku) {
            foreach ($this->zoho->get('items', ['sku' => $sku])['items'] ?? [] as $item) {
                if (ZohoStockSync::normalizeSku($item['sku'] ?? null) === $sku) {
                    $model?->forceFill(['zoho_item_id' => (string) $item['item_id']])->save();

                    return (string) $item['item_id'];
                }
            }
        }

        throw new RuntimeException('No Zoho item with SKU "' . ($sku ?? '(empty)') . '" for "' . ($model->name ?? 'unknown product') . '". Add the SKU in Zoho, then retry this order.');
    }

    // ── Invoice ─────────────────────────────────────────────────────────

    private function ensureInvoice(Order $order, string $contactId, array $salesOrder): array
    {
        $id = $order->zoho_invoice_id ?? $this->findByReference('invoices', 'invoices', 'invoice_id', $order->order_number);

        if (! $id) {
            $gst = $this->gstFields($order);

            $payload = array_filter([
                'customer_id' => $contactId,
                'reference_number' => $order->order_number,
                'date' => ($order->payment?->paid_at ?? now())->toDateString(),
                'line_items' => collect($salesOrder['line_items'] ?? [])->map(fn ($line) => [
                    'item_id' => (string) $line['item_id'],
                    'salesorder_item_id' => (string) $line['line_item_id'],
                    'quantity' => $line['quantity'],
                    'rate' => $line['rate'],
                ])->all(),
                'is_inclusive_tax' => (bool) config('services.zoho.prices_include_tax'),
                'gst_treatment' => $gst['gst_treatment'],
                'gst_no' => $gst['gst_no'],
                'place_of_supply' => $salesOrder['place_of_supply'] ?? $gst['place'],
                'shipping_charge' => (float) $order->delivery_charge ?: null,
                'notes' => "Website order {$order->order_number}",
            ] + $this->discountFields($order), fn ($v) => $v !== null && $v !== '');

            $response = $this->withPlaceFallback($payload, ['place_of_supply'], fn ($body) => $this->zoho->post('invoices', $body));
            $id = (string) $response['invoice']['invoice_id'];
            $order->forceFill(['zoho_invoice_id' => $id])->save();

            $this->zoho->post("invoices/{$id}/status/sent");
        } else {
            $order->forceFill(['zoho_invoice_id' => $id])->save();
        }

        $invoice = $this->zoho->get("invoices/{$id}")['invoice'];

        if (abs((float) $invoice['total'] - (float) $order->total_amount) >= 1) {
            $this->warnings[] = "Zoho invoice total ₹{$invoice['total']} differs from amount paid ₹{$order->total_amount} (check tax/shipping settings).";
        }

        return $invoice;
    }

    // ── Payment ─────────────────────────────────────────────────────────

    private function ensurePayment(Order $order, string $contactId, array $invoice): void
    {
        $balance = (float) ($invoice['balance'] ?? 0);

        // Already paid in Zoho (e.g. a retry after the payment was recorded)
        if ($order->zoho_payment_id || $balance <= 0) {
            return;
        }

        $paid = (float) ($order->payment?->amount ?? $order->total_amount);

        $response = $this->zoho->post('customerpayments', array_filter([
            'customer_id' => $contactId,
            'payment_mode' => config('services.zoho.payment_mode'),
            'amount' => $paid,
            'date' => ($order->payment?->paid_at ?? now())->toDateString(),
            'reference_number' => $order->payment?->transaction_id,
            'description' => "Razorpay payment for website order {$order->order_number}",
            'account_id' => config('services.zoho.payment_account_id'),
            'invoices' => [[
                'invoice_id' => (string) $invoice['invoice_id'],
                'amount_applied' => min($paid, $balance),
            ]],
        ]));

        $order->forceFill(['zoho_payment_id' => (string) $response['payment']['payment_id']])->save();
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function findByReference(string $path, string $key, string $idField, string $reference): ?string
    {
        foreach ($this->zoho->get($path, ['reference_number' => $reference])[$key] ?? [] as $record) {
            if (($record['reference_number'] ?? null) === $reference) {
                return (string) $record[$idField];
            }
        }

        return null;
    }

    private function gstFields(Order $order): array
    {
        $gstin = strtoupper(trim((string) $order->buyer_gstin));
        $hasGstin = (bool) preg_match('/^[0-9A-Z]{15}$/', $gstin);

        return [
            'gst_treatment' => $hasGstin ? 'business_gst' : 'consumer',
            'gst_no' => $hasGstin ? $gstin : null,
            'place' => IndianStates::code($order->delivery_state),
        ];
    }

    private function discountFields(Order $order): array
    {
        $discount = (float) $order->discount_amount;

        return $discount > 0
            ? ['discount' => $discount, 'discount_type' => 'entity_level', 'is_discount_before_tax' => true]
            : [];
    }

    private function address(Order $order): array
    {
        return array_filter([
            'address' => $order->delivery_address_line1,
            'street2' => $order->delivery_address_line2,
            'city' => $order->delivery_city,
            'state' => $order->delivery_state,
            'zip' => $order->delivery_postal_code,
            'country' => $order->delivery_country ?: 'India',
        ]);
    }

    /**
     * If Zoho rejects our state code, retry without it (Zoho then uses the
     * organisation's state) and flag the order so the GST place of supply is checked.
     */
    private function withPlaceFallback(array $payload, array $placeKeys, callable $send): array
    {
        try {
            return $send($payload);
        } catch (ZohoApiException $e) {
            $hasPlace = array_intersect_key($payload, array_flip($placeKeys));

            if (! $hasPlace || ! preg_match('/place|state/i', $e->getMessage())) {
                throw $e;
            }

            Log::warning('Zoho rejected place of supply, retrying without it: ' . $e->getMessage());
            $this->warnings[] = 'Zoho rejected the place of supply; it defaulted to your organisation\'s state. Check the GST type on this invoice.';

            return $send(array_diff_key($payload, array_flip($placeKeys)));
        }
    }
}
