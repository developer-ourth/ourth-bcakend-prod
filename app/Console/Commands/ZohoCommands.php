<?php

namespace App\Console\Commands;

use App\Jobs\PushOrderToZoho;
use App\Models\Order;
use App\Services\Zoho\ZohoInventoryClient;
use App\Services\Zoho\ZohoOrderSync;
use App\Services\Zoho\ZohoStockSync;
use Illuminate\Console\Command;

/**
 * zoho:connect {code}   – one-time setup: grant code → refresh token
 * zoho:check            – test the connection and show which stock fields Zoho returns
 * zoho:sync-stock       – Zoho stock → website (scheduled)
 * zoho:push-order {id}  – send one paid order to Zoho now
 * zoho:push-pending     – queue every paid order that isn't in Zoho yet
 */
class ZohoCommands extends Command
{
    protected $signature = 'zoho {action : connect|check|sync-stock|push-order|push-pending} {argument? : grant code or order id/number}';

    protected $description = 'Zoho Inventory integration tools';

    public function handle(ZohoInventoryClient $zoho): int
    {
        return match ($this->argument('action')) {
            'connect' => $this->connect($zoho),
            'check' => $this->check($zoho),
            'sync-stock' => $this->syncStock(),
            'push-order' => $this->pushOrder(),
            'push-pending' => $this->pushPending(),
            default => $this->invalid(),
        };
    }

    private function connect(ZohoInventoryClient $zoho): int
    {
        $code = $this->argument('argument');

        if (! $code) {
            $this->error('Usage: php artisan zoho connect <grant-code>');

            return self::FAILURE;
        }

        $tokens = $zoho->exchangeGrantCode($code);

        $this->info('Connected. Add this to the backend environment (Laravel Cloud → Environment variables):');
        $this->line('');
        $this->line('ZOHO_REFRESH_TOKEN=' . $tokens['refresh_token']);
        $this->line('');
        $this->warn('Keep it secret: it gives access to your Zoho Inventory until revoked.');

        return self::SUCCESS;
    }

    private function check(ZohoInventoryClient $zoho): int
    {
        $items = $zoho->get('items', ['per_page' => 5])['items'] ?? [];
        $this->info('Connected to Zoho Inventory organisation ' . config('services.zoho.organization_id') . '.');

        if (! $items) {
            $this->warn('No items found in Zoho.');

            return self::SUCCESS;
        }

        $fields = collect(['available_for_sale_stock', 'actual_available_stock', 'available_stock', 'stock_on_hand'])
            ->filter(fn ($field) => array_key_exists($field, $items[0]))
            ->values();

        $this->line('Stock fields on items: ' . ($fields->implode(', ') ?: 'none found'));
        $this->table(['SKU', 'Name', ...$fields], collect($items)->map(fn ($item) => [
            $item['sku'] ?? '', $item['name'] ?? '', ...$fields->map(fn ($f) => $item[$f] ?? ''),
        ]));

        return self::SUCCESS;
    }

    private function syncStock(): int
    {
        try {
            $status = app(ZohoStockSync::class)->run();
        } catch (\Throwable $e) {
            ZohoStockSync::recordFailure($e);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Synced {$status['packs_synced']} packs and {$status['products_synced']} products from {$status['zoho_items']} Zoho items (stock field: " . ($status['stock_field'] ?? 'none') . ').');

        if ($status['unmatched_count']) {
            $this->warn("{$status['unmatched_count']} website products/packs have no Zoho item with the same SKU:");
            $this->table(['Type', 'ID', 'Name', 'SKU'], collect($status['unmatched'])->map(fn ($u) => [$u['type'], $u['id'], $u['name'], $u['sku'] ?: '(empty)']));
        }

        return self::SUCCESS;
    }

    private function pushOrder(): int
    {
        $ref = $this->argument('argument');
        $order = Order::where('id', $ref)->orWhere('order_number', $ref)->first();

        if (! $order) {
            $this->error("Order {$ref} not found.");

            return self::FAILURE;
        }

        if (! ZohoOrderSync::shouldSync($order)) {
            $this->warn('Order is not paid, is cancelled, or is older than ZOHO_ORDERS_FROM; not sent.');

            return self::FAILURE;
        }

        app(ZohoOrderSync::class)->push($order);
        $order->refresh();
        $this->info("Order {$order->order_number} → Zoho sales order {$order->zoho_salesorder_id}, invoice {$order->zoho_invoice_id}, payment {$order->zoho_payment_id}.");

        if ($order->zoho_sync_error) {
            $this->warn($order->zoho_sync_error);
        }

        return self::SUCCESS;
    }

    private function pushPending(): int
    {
        $count = 0;

        Order::where('payment_status', 'paid')
            ->where('order_status', '!=', 'cancelled')
            ->whereNull('zoho_synced_at')
            ->when(config('services.zoho.orders_from'), fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->pluck('id')
            ->each(function ($id) use (&$count) {
                PushOrderToZoho::dispatch($id);
                $count++;
            });

        $this->info("Queued {$count} paid orders for Zoho.");

        return self::SUCCESS;
    }

    private function invalid(): int
    {
        $this->error('Unknown action. Use: connect, check, sync-stock, push-order, push-pending.');

        return self::FAILURE;
    }
}
