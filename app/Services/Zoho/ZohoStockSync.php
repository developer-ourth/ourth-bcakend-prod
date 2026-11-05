<?php

namespace App\Services\Zoho;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductPack;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Zoho Inventory is the source of truth for stock. Each run reads every Zoho
 * item, matches it to website products / packs by SKU, and sets:
 *
 *   website available = Zoho available-for-sale − website orders not yet in Zoho
 *
 * so stock reserved by a customer who is still paying is not sold twice.
 */
class ZohoStockSync
{
    public const STATUS_CACHE_KEY = 'zoho:stock-sync-status';

    /** Zoho's stock fields, most precise first. Which ones exist depends on the org's settings. */
    private const STOCK_FIELDS = ['available_for_sale_stock', 'actual_available_stock', 'available_stock', 'stock_on_hand'];

    /** Website orders not yet in Zoho stop holding stock after this long. */
    private const RESERVATION_HOURS = 72;

    public function __construct(private ZohoInventoryClient $zoho) {}

    public function run(): array
    {
        $items = $this->zoho->all('items', 'items', ['filter_by' => 'Status.Active']);

        $zohoBySku = [];
        $stockField = null;

        foreach ($items as $item) {
            $sku = self::normalizeSku($item['sku'] ?? null);

            if (! $sku) {
                continue;
            }

            $field = $this->stockFieldFor($item);
            $stockField ??= $field;
            $zohoBySku[$sku] = [
                'item_id' => (string) $item['item_id'],
                'stock' => $field ? (int) floor((float) $item[$field]) : null,
            ];
        }

        [$pendingByPack, $pendingByProduct] = $this->pendingReservations();
        $now = now();
        $updatedPacks = $updatedProducts = 0;
        $unmatched = [];

        DB::transaction(function () use ($zohoBySku, $pendingByPack, $pendingByProduct, $now, &$updatedPacks, &$updatedProducts, &$unmatched) {
            ProductPack::query()->where('is_active', true)->with('product:id,name')->chunkById(200, function ($packs) use ($zohoBySku, $pendingByPack, $now, &$updatedPacks, &$unmatched) {
                foreach ($packs as $pack) {
                    $match = $zohoBySku[self::normalizeSku($pack->sku)] ?? null;

                    if (! $match) {
                        $unmatched[] = ['type' => 'pack', 'id' => $pack->id, 'name' => trim(($pack->product?->name ?? '') . ' (' . $pack->name . ')'), 'sku' => $pack->sku];
                        continue;
                    }

                    $pack->zoho_item_id = $match['item_id'];
                    $pack->zoho_stock_synced_at = $now;

                    if ($match['stock'] !== null) {
                        $pack->stock_quantity = max(0, $match['stock'] - ($pendingByPack[$pack->id] ?? 0));
                    }

                    $pack->save();
                    $updatedPacks++;
                }
            });

            Product::query()->where('is_active', true)->with('inventory')->withCount('packs')->chunkById(200, function ($products) use ($zohoBySku, $pendingByProduct, $now, &$updatedProducts, &$unmatched) {
                foreach ($products as $product) {
                    $match = $zohoBySku[self::normalizeSku($product->sku)] ?? null;

                    if (! $match) {
                        // Products sold only as packs are matched through their packs.
                        if ($product->packs_count === 0) {
                            $unmatched[] = ['type' => 'product', 'id' => $product->id, 'name' => $product->name, 'sku' => $product->sku];
                        }
                        continue;
                    }

                    $product->zoho_item_id = $match['item_id'];
                    $product->zoho_stock_synced_at = $now;

                    if ($match['stock'] !== null) {
                        $reserved = $pendingByProduct[$product->id] ?? 0;
                        $product->stock_quantity = max(0, $match['stock'] - $reserved);

                        $inventory = $product->inventory ?? new Inventory(['product_id' => $product->id, 'vendor_id' => $product->vendor_id]);
                        $inventory->current_stock = max(0, $match['stock']);
                        $inventory->reserved_stock = min($reserved, max(0, $match['stock']));
                        if ($inventory->isDirty()) {
                            $inventory->save();
                        }
                    }

                    $product->save();
                    $updatedProducts++;
                }
            });
        });

        $status = [
            'ran_at' => $now->toIso8601String(),
            'zoho_items' => count($items),
            'zoho_items_with_sku' => count($zohoBySku),
            'stock_field' => $stockField,
            'packs_synced' => $updatedPacks,
            'products_synced' => $updatedProducts,
            'unmatched' => array_slice($unmatched, 0, 200),
            'unmatched_count' => count($unmatched),
            'error' => null,
        ];

        Cache::forever(self::STATUS_CACHE_KEY, $status);

        if ($stockField === null && $zohoBySku) {
            Log::warning('Zoho stock sync: no stock field found on Zoho items; stock was not changed. Set ZOHO_STOCK_FIELD.');
        }

        return $status;
    }

    public static function recordFailure(\Throwable $e): void
    {
        $status = Cache::get(self::STATUS_CACHE_KEY, []);
        $status['error'] = $e->getMessage();
        $status['failed_at'] = now()->toIso8601String();
        Cache::forever(self::STATUS_CACHE_KEY, $status);
    }

    public static function normalizeSku(?string $sku): ?string
    {
        $sku = strtoupper(trim((string) $sku));

        return $sku === '' ? null : $sku;
    }

    private function stockFieldFor(array $item): ?string
    {
        $configured = config('services.zoho.stock_field');

        if ($configured) {
            return array_key_exists($configured, $item) ? $configured : null;
        }

        foreach (self::STOCK_FIELDS as $field) {
            if (isset($item[$field]) && is_numeric($item[$field])) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Quantities in website orders that Zoho doesn't know about yet: not cancelled,
     * not failed, no sales order yet, placed within the reservation window.
     *
     * @return array{0: array<int,int>, 1: array<int,int>} [pack_id => qty, product_id => qty]
     */
    private function pendingReservations(): array
    {
        $since = now()->subHours(self::RESERVATION_HOURS);
        if ($from = config('services.zoho.orders_from')) {
            $since = Carbon::parse($from)->max($since);
        }

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.zoho_salesorder_id')
            ->where('orders.order_status', '!=', 'cancelled')
            ->where('orders.payment_status', '!=', 'failed')
            ->where('orders.created_at', '>=', $since)
            ->groupBy('order_items.product_id', 'order_items.product_pack_id')
            ->select('order_items.product_id', 'order_items.product_pack_id', DB::raw('SUM(order_items.quantity) as qty'))
            ->get();

        $byPack = $byProduct = [];
        foreach ($rows as $row) {
            if ($row->product_pack_id) {
                $byPack[$row->product_pack_id] = (int) $row->qty;
            } else {
                $byProduct[$row->product_id] = (int) $row->qty;
            }
        }

        return [$byPack, $byProduct];
    }
}
