<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Alert;
use App\Models\ImpactMetric;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDailyStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FounderDashboardController - CXO / Command Center Dashboard
 *
 * Provides one-glance business metrics for founders and C-level executives.
 */
class FounderDashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard/founder
     *
     * Returns consolidated KPIs: revenue, orders, vendors, waste, city performance, and alerts.
     */
    public function index(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $revenueToday = (float) Order::whereDate('created_at', $today)
            ->whereIn('order_status', ['delivered', 'confirmed', 'dispatched'])
            ->sum('total_amount');

        $revenueYesterday = (float) Order::whereDate('created_at', $yesterday)
            ->whereIn('order_status', ['delivered', 'confirmed', 'dispatched'])
            ->sum('total_amount');

        $revenueThisMonth = (float) Order::whereBetween('created_at', [$monthStart.' 00:00:00', now()])
            ->whereIn('order_status', ['delivered', 'confirmed', 'dispatched'])
            ->sum('total_amount');

        $ordersToday = Order::whereDate('created_at', $today)->count();
        $ordersThisMonth = Order::whereBetween('created_at', [$monthStart.' 00:00:00', now()])->count();

        $ordersByType = Order::whereDate('created_at', $today)
            ->select('order_status', DB::raw('count(*) as count'))
            ->groupBy('order_status')
            ->pluck('count', 'order_status');

        $totalVendors = Vendor::count();
        $activeVendors = Vendor::where('kyc_status', 'verified')->count();
        $pendingKyc = Vendor::where('kyc_status', 'under_review')->count();

        $impactToday = ImpactMetric::whereDate('metric_date', $today)->first();
        $impactThisMonth = ImpactMetric::whereBetween('metric_date', [$monthStart, $today])
            ->selectRaw('
                SUM(plastic_avoided_kg) as plastic_avoided_kg,
                SUM(landfill_reduction_kg) as landfill_reduction_kg,
                SUM(co2_saved_kg) as co2_saved_kg,
                SUM(total_waste_collected_kg) as total_waste_collected_kg
            ')
            ->first();

        $cityPerformance = VendorDailyStat::join('vendors', 'vendor_daily_stats.vendor_id', '=', 'vendors.id')
            ->whereBetween('stats_date', [$monthStart, $today])
            ->select('vendors.city as city', DB::raw('SUM(vendor_daily_stats.total_revenue) as revenue'), DB::raw('SUM(vendor_daily_stats.total_orders) as orders'))
            ->groupBy('vendors.city')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        $activeAlerts = Alert::unresolved()
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'alert_type', 'severity', 'title', 'message', 'city', 'created_at']);

        $criticalAlertsCount = Alert::unresolved()->critical()->count();

        return response()->json([
            'revenue' => [
                'today' => $revenueToday,
                'yesterday' => $revenueYesterday,
                'this_month' => $revenueThisMonth,
                'day_over_day_change_percent' => $revenueYesterday > 0
                    ? round((($revenueToday - $revenueYesterday) / $revenueYesterday) * 100, 2)
                    : null,
            ],
            'orders' => [
                'today' => $ordersToday,
                'this_month' => $ordersThisMonth,
                'by_status' => $ordersByType,
            ],
            'vendors' => [
                'total' => $totalVendors,
                'active' => $activeVendors,
                'pending_kyc' => $pendingKyc,
            ],
            'waste_impact' => [
                'today' => $impactToday ? [
                    'plastic_avoided_kg' => $impactToday->plastic_avoided_kg,
                    'co2_saved_kg' => $impactToday->co2_saved_kg,
                    'collections_completed' => $impactToday->collections_completed,
                ] : null,
                'this_month' => $impactThisMonth ? [
                    'plastic_avoided_kg' => $impactThisMonth->plastic_avoided_kg,
                    'landfill_reduction_kg' => $impactThisMonth->landfill_reduction_kg,
                    'co2_saved_kg' => $impactThisMonth->co2_saved_kg,
                    'total_waste_collected_kg' => $impactThisMonth->total_waste_collected_kg,
                ] : null,
            ],
            'city_performance' => $cityPerformance,
            'alerts' => [
                'critical_count' => $criticalAlertsCount,
                'items' => $activeAlerts,
            ],
        ]);
    }

    /**
     * GET /api/v1/dashboard/founder/kpis
     *
     * Returns high-level KPI trend data for charts (last 30 days).
     */
    public function kpis(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $days = min($days, 90);

        $revenueByDay = Order::whereIn('order_status', ['delivered', 'confirmed', 'dispatched'])
            ->where('created_at', '>=', now()->subDays($days))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as orders'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $vendorGrowth = Vendor::where('created_at', '>=', now()->subDays($days))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as new_vendors'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $userGrowth = User::where('role', 'consumer')
            ->where('created_at', '>=', now()->subDays($days))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as new_consumers'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'days' => $days,
            'revenue_trend' => $revenueByDay,
            'vendor_growth' => $vendorGrowth,
            'consumer_growth' => $userGrowth,
        ]);
    }

    /**
     * GET /api/v1/dashboard/founder/products
     *
     * Returns product catalog with full details for founder/admin dashboards.
     */
    public function products(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'vendor'])
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                    ->orWhere('description', 'ilike', "%{$term}%")
                    ->orWhere('sku', 'ilike', "%{$term}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $products = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products->items()),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }
}
