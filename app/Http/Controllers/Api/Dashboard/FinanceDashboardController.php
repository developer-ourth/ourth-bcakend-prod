<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\FinancialDailySnapshot;
use App\Models\ImpactMetric;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDailyStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FinanceDashboardController - Finance & Investor Dashboard
 *
 * Provides live financial data: revenue streams, unit economics, burn, runway, ESG.
 */
class FinanceDashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard/finance
     */
    public function index(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $lastMonthStart = now()->subMonth()->startOfMonth()->toDateString();
        $lastMonthEnd = now()->subMonth()->endOfMonth()->toDateString();

        $latestSnapshot = FinancialDailySnapshot::orderByDesc('snapshot_date')->first();

        $revenueThisMonth = (float) Order::whereBetween('created_at', [$monthStart.' 00:00:00', now()])
            ->whereIn('order_status', ['delivered', 'confirmed', 'dispatched'])
            ->sum('total_amount');

        $revenueLastMonth = (float) Order::whereBetween('created_at', [$lastMonthStart.' 00:00:00', $lastMonthEnd.' 23:59:59'])
            ->whereIn('order_status', ['delivered', 'confirmed', 'dispatched'])
            ->sum('total_amount');

        $subscriptionRevenue = (float) Subscription::where('status', 'active')
            ->sum('plan_price');

        $totalOrdersThisMonth = Order::whereBetween('created_at', [$monthStart.' 00:00:00', now()])->count();

        $avgOrderValue = $totalOrdersThisMonth > 0
            ? round($revenueThisMonth / $totalOrdersThisMonth, 2)
            : 0;

        $activeVendors = Vendor::where('kyc_status', 'verified')->count();
        $avgRevenuePerVendor = $activeVendors > 0
            ? round($revenueThisMonth / $activeVendors, 2)
            : 0;

        $newCustomersThisMonth = User::where('user_type', 'consumer')
            ->whereBetween('created_at', [$monthStart.' 00:00:00', now()])
            ->count();

        $revenueTrend = FinancialDailySnapshot::where('snapshot_date', '>=', now()->subDays(30)->toDateString())
            ->orderBy('snapshot_date')
            ->get([
                'snapshot_date', 'total_revenue', 'product_revenue', 'subscription_revenue',
                'service_revenue', 'daily_burn_rate', 'cash_balance', 'runway_days',
            ]);

        $monthlyImpactForESG = ImpactMetric::whereBetween('metric_date', [$monthStart, $today])
            ->selectRaw('
                SUM(plastic_avoided_kg) as plastic_avoided_kg,
                SUM(landfill_reduction_kg) as landfill_reduction_kg,
                SUM(co2_saved_kg) as co2_saved_kg,
                SUM(eco_orders_count) as eco_orders_count
            ')
            ->first();

        $revenueByCity = VendorDailyStat::join('vendors', 'vendor_daily_stats.vendor_id', '=', 'vendors.id')
            ->whereBetween('stats_date', [$monthStart, $today])
            ->select('vendors.city as city', DB::raw('SUM(vendor_daily_stats.total_revenue) as revenue'), DB::raw('SUM(vendor_daily_stats.total_orders) as orders'))
            ->groupBy('vendors.city')
            ->orderByDesc('revenue')
            ->get();

        return response()->json([
            'revenue' => [
                'this_month' => $revenueThisMonth,
                'last_month' => $revenueLastMonth,
                'mom_growth_percent' => $revenueLastMonth > 0
                    ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 2)
                    : null,
                'product_revenue' => $revenueThisMonth - $subscriptionRevenue,
                'subscription_revenue' => $subscriptionRevenue,
                'service_revenue' => 0,
            ],
            'unit_economics' => [
                'avg_order_value' => $avgOrderValue,
                'avg_revenue_per_vendor' => $avgRevenuePerVendor,
                'total_orders_this_month' => $totalOrdersThisMonth,
                'active_vendors' => $activeVendors,
                'new_customers_this_month' => $newCustomersThisMonth,
            ],
            'cac_ltv' => [
                'cac' => $latestSnapshot?->cac ?? 0,
                'ltv' => $latestSnapshot?->ltv ?? 0,
                'ltv_cac_ratio' => $latestSnapshot?->cac > 0
                    ? round($latestSnapshot->ltv / $latestSnapshot->cac, 2)
                    : null,
            ],
            'burn_runway' => [
                'daily_burn_rate' => $latestSnapshot?->daily_burn_rate ?? 0,
                'cash_balance' => $latestSnapshot?->cash_balance ?? 0,
                'runway_days' => $latestSnapshot?->runway_days ?? 0,
            ],
            'revenue_trend_30d' => $revenueTrend,
            'revenue_by_city' => $revenueByCity,
            'esg_metrics' => $monthlyImpactForESG ? [
                'plastic_avoided_kg' => $monthlyImpactForESG->plastic_avoided_kg,
                'landfill_reduction_kg' => $monthlyImpactForESG->landfill_reduction_kg,
                'co2_saved_kg' => $monthlyImpactForESG->co2_saved_kg,
                'eco_orders_count' => $monthlyImpactForESG->eco_orders_count,
            ] : null,
        ]);
    }

    /**
     * GET /api/v1/dashboard/finance/snapshots
     *
     * Returns paginated financial daily snapshots.
     */
    public function snapshots(Request $request): JsonResponse
    {
        $snapshots = FinancialDailySnapshot::orderByDesc('snapshot_date')->paginate(30);

        return response()->json($snapshots);
    }
}
