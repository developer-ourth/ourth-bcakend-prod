<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PushOrderToZoho;
use App\Jobs\SyncZohoStock;
use App\Models\Order;
use App\Services\Zoho\ZohoInventoryClient;
use App\Services\Zoho\ZohoOrderSync;
use App\Services\Zoho\ZohoStockSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ZohoInventoryController extends Controller
{
    /**
     * Connection status, last stock sync (with unmatched SKUs) and orders needing attention.
     */
    public function status(ZohoInventoryClient $zoho): JsonResponse
    {
        $from = config('services.zoho.orders_from');

        $paid = Order::where('payment_status', 'paid')
            ->where('order_status', '!=', 'cancelled')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from));

        $attention = (clone $paid)
            ->where(fn ($q) => $q->whereNull('zoho_synced_at')->orWhereNotNull('zoho_sync_error'))
            ->where('created_at', '<=', now()->subMinutes(5)) // give the queue a moment first
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'order_number', 'total_amount', 'created_at', 'zoho_salesorder_id', 'zoho_invoice_id', 'zoho_synced_at', 'zoho_sync_error']);

        return response()->json([
            'success' => true,
            'data' => [
                'configured' => $zoho->isConfigured(),
                'organization_id' => config('services.zoho.organization_id'),
                'stock_sync_minutes' => config('services.zoho.stock_sync_minutes'),
                'orders_from' => $from,
                'stock_sync' => Cache::get(ZohoStockSync::STATUS_CACHE_KEY),
                'orders_synced' => (clone $paid)->whereNotNull('zoho_synced_at')->count(),
                'orders_needing_attention' => $attention,
            ],
        ]);
    }

    public function syncStock(ZohoInventoryClient $zoho): JsonResponse
    {
        if (! $zoho->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'Zoho Inventory is not connected yet.'], 422);
        }

        SyncZohoStock::dispatch();

        return response()->json(['success' => true, 'message' => 'Stock sync started. Refresh in a minute to see the result.']);
    }

    public function retryOrder(Order $order, ZohoInventoryClient $zoho): JsonResponse
    {
        if (! $zoho->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'Zoho Inventory is not connected yet.'], 422);
        }

        if (! ZohoOrderSync::shouldSync($order)) {
            return response()->json(['success' => false, 'message' => 'Only paid, non-cancelled orders are sent to Zoho.'], 422);
        }

        PushOrderToZoho::dispatch($order->id, force: true);

        return response()->json(['success' => true, 'message' => "Order {$order->order_number} queued for Zoho."]);
    }
}
