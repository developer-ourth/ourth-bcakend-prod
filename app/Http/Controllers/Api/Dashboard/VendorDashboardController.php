<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\QrScanLog;
use App\Models\Vendor;
use App\Models\VendorDailyStat;
use App\Models\VendorQrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * VendorDashboardController - Vendor / Hawker Operational Dashboard
 *
 * Provides vendor-level metrics: orders, earnings, inventory, QR usage.
 */
class VendorDashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard/vendor/{vendor}
     */
    public function index(Request $request, Vendor $vendor): JsonResponse
    {
        $today = now()->toDateString();
        $weekStart = now()->startOfWeek()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $todayStat = VendorDailyStat::where('vendor_id', $vendor->id)
            ->whereDate('stats_date', $today)
            ->first();

        $weekStats = VendorDailyStat::where('vendor_id', $vendor->id)
            ->whereBetween('stats_date', [$weekStart, $today])
            ->selectRaw('SUM(total_orders) as orders, SUM(total_revenue) as revenue, SUM(delivered_orders) as delivered, SUM(cancelled_orders) as cancelled')
            ->first();

        $pendingOrders = Order::where('vendor_id', $vendor->id)
            ->whereIn('order_status', ['pending', 'confirmed'])
            ->count();

        $lowStockItems = Inventory::where('vendor_id', $vendor->id)
            ->whereRaw('current_stock <= minimum_stock_level')
            ->with('product:id,name,sku')
            ->get(['product_id', 'current_stock', 'minimum_stock_level', 'reorder_quantity']);

        $qrCode = VendorQrCode::where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->first(['id', 'qr_code_id', 'qr_code_image_url', 'scans_count', 'last_scanned_at']);

        $recentScans = $qrCode ? QrScanLog::where('vendor_qr_code_id', $qrCode->id)
            ->latest()
            ->limit(5)
            ->get(['id', 'scan_context', 'created_at'])
            : collect();

        $recentOrders = Order::where('vendor_id', $vendor->id)
            ->latest()
            ->limit(10)
            ->get(['id', 'order_number', 'order_status', 'payment_status', 'total_amount', 'created_at']);

        $reorderAlerts = Inventory::where('vendor_id', $vendor->id)
            ->whereRaw('current_stock <= minimum_stock_level')
            ->with('product:id,name,sku,base_price')
            ->get();

        return response()->json([
            'vendor' => [
                'id' => $vendor->id,
                'business_name' => $vendor->business_name,
                'city' => $vendor->city,
                'kyc_status' => $vendor->kyc_status,
                'average_rating' => $vendor->average_rating,
                'total_orders' => $vendor->total_orders,
                'total_revenue' => $vendor->total_revenue,
            ],
            'today' => [
                'orders' => $todayStat?->total_orders ?? 0,
                'revenue' => $todayStat?->total_revenue ?? 0,
                'delivered' => $todayStat?->delivered_orders ?? 0,
                'cancelled' => $todayStat?->cancelled_orders ?? 0,
                'average_order_value' => $todayStat?->average_order_value ?? 0,
            ],
            'this_week' => [
                'orders' => $weekStats?->orders ?? 0,
                'revenue' => $weekStats?->revenue ?? 0,
                'delivered' => $weekStats?->delivered ?? 0,
                'cancelled' => $weekStats?->cancelled ?? 0,
            ],
            'pending_orders' => $pendingOrders,
            'low_stock_count' => $lowStockItems->count(),
            'reorder_alerts' => $reorderAlerts->map(fn ($inv) => [
                'product_name' => $inv->product?->name,
                'sku' => $inv->product?->sku,
                'current_stock' => $inv->current_stock,
                'minimum_stock_level' => $inv->minimum_stock_level,
                'reorder_quantity' => $inv->reorder_quantity,
            ]),
            'qr_code' => $qrCode ? [
                'qr_code_id' => $qrCode->qr_code_id,
                'image_url' => $qrCode->qr_code_image_url,
                'scans_count' => $qrCode->scans_count,
                'last_scanned_at' => $qrCode->last_scanned_at,
                'recent_scans' => $recentScans,
            ] : null,
            'recent_orders' => $recentOrders,
        ]);
    }

    /**
     * GET /api/v1/dashboard/vendor/{vendor}/earnings
     *
     * Returns earnings trend for the vendor (last 30 days).
     */
    public function earnings(Request $request, Vendor $vendor): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $days = min($days, 90);

        $stats = VendorDailyStat::where('vendor_id', $vendor->id)
            ->where('stats_date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('stats_date')
            ->get(['stats_date', 'total_orders', 'total_revenue', 'delivered_orders', 'cancelled_orders', 'average_order_value']);

        return response()->json([
            'vendor_id' => $vendor->id,
            'days' => $days,
            'earnings_trend' => $stats,
        ]);
    }

    /**
     * GET /api/v1/dashboard/vendor/{vendor}/catalog
     *
     * Returns vendor product catalog with current inventory levels.
     */
    public function catalog(Request $request, Vendor $vendor): JsonResponse
    {
        $products = $vendor->products()
            ->with('inventory')
            ->where('is_active', true)
            ->get(['id', 'name', 'sku', 'base_price', 'discounted_price', 'category', 'is_active', 'primary_image_url']);

        return response()->json([
            'vendor_id' => $vendor->id,
            'products' => $products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'base_price' => $p->base_price,
                'discounted_price' => $p->discounted_price,
                'category' => $p->category,
                'image_url' => $p->primary_image_url,
                'current_stock' => $p->inventory?->current_stock ?? 0,
                'available_stock' => $p->inventory?->available_stock ?? 0,
                'is_low_stock' => $p->inventory?->isLowStock() ?? false,
            ]),
        ]);
    }
}
