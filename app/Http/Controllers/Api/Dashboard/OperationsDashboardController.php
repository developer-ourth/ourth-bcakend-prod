<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * OperationsDashboardController - Operations & Logistics Dashboard
 *
 * Provides supply chain visibility: dispatch, deliveries, inventory, SLA metrics.
 */
class OperationsDashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard/operations
     */
    public function index(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        $ordersToDispatch = Order::whereIn('order_status', ['pending', 'confirmed'])
            ->count();

        $ordersInTransit = Order::where('order_status', 'dispatched')
            ->count();

        $deliveriesByStatus = Delivery::select('delivery_status', DB::raw('count(*) as count'))
            ->groupBy('delivery_status')
            ->pluck('count', 'delivery_status');

        $deliveriesToday = Delivery::whereDate('created_at', $today)->count();
        $deliveredToday = Delivery::whereDate('delivered_at', $today)->count();
        $failedToday = Delivery::whereDate('created_at', $today)
            ->where('delivery_status', 'failed')
            ->count();

        $slaBreaches = Order::where('order_status', 'dispatched')
            ->where('dispatched_at', '<=', now()->subHours(24))
            ->count();

        $avgDeliveryTime = Delivery::whereNotNull('delivered_at')
            ->whereNotNull('assigned_at')
            ->whereDate('delivered_at', $today)
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (delivered_at - assigned_at)) / 60) as avg_minutes')
            ->value('avg_minutes');

        $activeRoutes = DeliveryRoute::where('route_date', $today)
            ->whereIn('status', ['planned', 'in_progress'])
            ->with('deliveryPartner:id,name,phone')
            ->get(['id', 'route_number', 'status', 'total_stops', 'completed_stops', 'delivery_partner_id', 'total_distance_km']);

        $warehouseStockAlerts = Inventory::whereRaw('current_stock <= minimum_stock_level')
            ->with('product:id,name,sku', 'vendor:id,business_name')
            ->limit(20)
            ->get();

        $warehouses = Warehouse::where('status', 'active')
            ->withCount('inventory')
            ->get(['id', 'name', 'code', 'city', 'status', 'total_capacity_units']);

        $deliveryPartnerPerformance = User::where('role', 'delivery_partner')
            ->withCount([
                'deliveryOrders as total_assigned' => fn ($q) => $q->whereDate('created_at', now()->subDays(7)),
                'deliveryOrders as delivered_count' => fn ($q) => $q->where('delivery_status', 'delivered')->whereDate('delivered_at', now()->subDays(7)),
            ])
            ->get(['id', 'name', 'phone'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'total_assigned' => $u->total_assigned,
                'delivered_count' => $u->delivered_count,
                'success_rate' => $u->total_assigned > 0
                    ? round(($u->delivered_count / $u->total_assigned) * 100, 1)
                    : 0,
            ]);

        return response()->json([
            'orders' => [
                'to_dispatch' => $ordersToDispatch,
                'in_transit' => $ordersInTransit,
                'sla_breaches' => $slaBreaches,
            ],
            'deliveries' => [
                'today_total' => $deliveriesToday,
                'today_delivered' => $deliveredToday,
                'today_failed' => $failedToday,
                'avg_delivery_time_minutes' => $avgDeliveryTime ? round($avgDeliveryTime) : null,
                'by_status' => $deliveriesByStatus,
            ],
            'active_routes' => $activeRoutes,
            'warehouses' => $warehouses,
            'stock_alerts_count' => $warehouseStockAlerts->count(),
            'stock_alerts' => $warehouseStockAlerts->map(fn ($inv) => [
                'product_name' => $inv->product?->name,
                'sku' => $inv->product?->sku,
                'vendor_name' => $inv->vendor?->business_name,
                'current_stock' => $inv->current_stock,
                'minimum_stock_level' => $inv->minimum_stock_level,
            ]),
            'delivery_partner_performance' => $deliveryPartnerPerformance,
        ]);
    }

    /**
     * GET /api/v1/dashboard/operations/routes
     *
     * Returns delivery routes for a given date (defaults to today).
     */
    public function routes(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());
        $city = $request->query('city');

        $query = DeliveryRoute::whereDate('route_date', $date)
            ->with('deliveryPartner:id,name,phone');

        if ($city) {
            $query->where('city', $city);
        }

        $routes = $query->get();

        return response()->json([
            'date' => $date,
            'routes' => $routes,
        ]);
    }

    /**
     * GET /api/v1/dashboard/operations/inventory
     *
     * Returns warehouse inventory overview.
     */
    public function inventory(Request $request): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id');

        $query = Inventory::with('product:id,name,sku,category', 'vendor:id,business_name');

        if ($warehouseId) {
            $query->where('vendor_id', $warehouseId);
        }

        $items = $query->orderByRaw('current_stock <= minimum_stock_level DESC')
            ->paginate(50);

        return response()->json($items);
    }
}
