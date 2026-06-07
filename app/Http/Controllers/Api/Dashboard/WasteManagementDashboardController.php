<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Dustbin;
use App\Models\ImpactMetric;
use App\Models\RecyclingRecord;
use App\Models\WasteCollection;
use App\Models\WasteSegregationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * WasteManagementDashboardController - Circular Economy & ESG Dashboard
 *
 * Tracks QR dustbin fill levels, collection routes, segregation, recycling, and impact.
 */
class WasteManagementDashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard/waste-management
     */
    public function index(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $city = $request->query('city');

        $binQuery = Dustbin::where('status', 'active');
        $collectionQuery = WasteCollection::whereDate('scheduled_date', $today);
        $impactQuery = ImpactMetric::whereBetween('metric_date', [$monthStart, $today]);

        if ($city) {
            $binQuery->where('city', $city);
            $impactQuery->where('city', $city);
        }

        $totalBins = $binQuery->count();
        $fullBins = (clone $binQuery)->where('fill_level_percent', '>=', 90)->count();
        $binsByType = (clone $binQuery)->select('bin_type', DB::raw('count(*) as count'))
            ->groupBy('bin_type')
            ->pluck('count', 'bin_type');
        $avgFillLevel = (clone $binQuery)->avg('fill_level_percent');

        $todayCollections = (clone $collectionQuery)->count();
        $completedCollections = (clone $collectionQuery)->where('status', 'completed')->count();
        $pendingCollections = (clone $collectionQuery)->whereIn('status', ['scheduled', 'in_progress'])->count();

        $monthlyImpact = (clone $impactQuery)
            ->selectRaw('
                SUM(plastic_avoided_kg) as plastic_avoided_kg,
                SUM(landfill_reduction_kg) as landfill_reduction_kg,
                SUM(co2_saved_kg) as co2_saved_kg,
                SUM(total_waste_collected_kg) as total_waste_collected_kg,
                SUM(collections_completed) as collections_completed,
                AVG(recycling_rate_percent) as avg_recycling_rate
            ')
            ->first();

        $segregationThisMonth = WasteSegregationLog::join('waste_collections', 'waste_segregation_logs.waste_collection_id', '=', 'waste_collections.id')
            ->whereBetween('waste_collections.scheduled_date', [$monthStart, $today])
            ->selectRaw('
                SUM(dry_waste_kg) as dry_waste_kg,
                SUM(wet_waste_kg) as wet_waste_kg,
                SUM(plastic_waste_kg) as plastic_waste_kg,
                SUM(e_waste_kg) as e_waste_kg,
                SUM(hazardous_waste_kg) as hazardous_waste_kg,
                SUM(other_waste_kg) as other_waste_kg
            ')
            ->first();

        $recyclingThisMonth = RecyclingRecord::whereBetween('processing_date', [$monthStart, $today])
            ->selectRaw('
                SUM(input_weight_kg) as input_kg,
                SUM(recycled_weight_kg) as recycled_kg,
                AVG(recycling_efficiency_percent) as avg_efficiency,
                SUM(co2_saved_kg) as co2_saved_kg
            ')
            ->when($city, fn ($q) => $q->where('facility_city', $city))
            ->first();

        $binsNeedingCollection = Dustbin::where('fill_level_percent', '>=', 75)
            ->where('status', 'active')
            ->when($city, fn ($q) => $q->where('city', $city))
            ->orderByDesc('fill_level_percent')
            ->limit(10)
            ->get(['id', 'bin_label', 'qr_code', 'city', 'area', 'fill_level_percent', 'bin_type', 'last_emptied_at']);

        $impactTrend = ImpactMetric::where('metric_date', '>=', now()->subDays(30)->toDateString())
            ->when($city, fn ($q) => $q->where('city', $city))
            ->orderBy('metric_date')
            ->get(['metric_date', 'plastic_avoided_kg', 'co2_saved_kg', 'total_waste_collected_kg', 'recycling_rate_percent']);

        return response()->json([
            'dustbins' => [
                'total' => $totalBins,
                'full' => $fullBins,
                'avg_fill_level_percent' => round($avgFillLevel ?? 0, 1),
                'by_type' => $binsByType,
            ],
            'collections_today' => [
                'total' => $todayCollections,
                'completed' => $completedCollections,
                'pending' => $pendingCollections,
            ],
            'monthly_impact' => $monthlyImpact ? [
                'plastic_avoided_kg' => $monthlyImpact->plastic_avoided_kg,
                'landfill_reduction_kg' => $monthlyImpact->landfill_reduction_kg,
                'co2_saved_kg' => $monthlyImpact->co2_saved_kg,
                'total_waste_collected_kg' => $monthlyImpact->total_waste_collected_kg,
                'collections_completed' => $monthlyImpact->collections_completed,
                'avg_recycling_rate' => round($monthlyImpact->avg_recycling_rate ?? 0, 2),
            ] : null,
            'segregation_this_month' => $segregationThisMonth,
            'recycling_this_month' => $recyclingThisMonth ? [
                'input_kg' => $recyclingThisMonth->input_kg,
                'recycled_kg' => $recyclingThisMonth->recycled_kg,
                'avg_efficiency_percent' => round($recyclingThisMonth->avg_efficiency ?? 0, 2),
                'co2_saved_kg' => $recyclingThisMonth->co2_saved_kg,
            ] : null,
            'bins_needing_collection' => $binsNeedingCollection,
            'impact_trend_30d' => $impactTrend,
        ]);
    }

    /**
     * GET /api/v1/dashboard/waste-management/dustbins
     *
     * Returns all dustbins with pagination and filtering.
     */
    public function dustbins(Request $request): JsonResponse
    {
        $query = Dustbin::with('assignedVendor:id,business_name');

        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('bin_type')) {
            $query->where('bin_type', $request->bin_type);
        }

        $dustbins = $query->orderByDesc('fill_level_percent')->paginate(50);

        return response()->json($dustbins);
    }

    /**
     * GET /api/v1/dashboard/waste-management/collections
     *
     * Returns waste collection schedule/history.
     */
    public function collections(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());

        $collections = WasteCollection::whereDate('scheduled_date', $date)
            ->with([
                'dustbin:id,bin_label,city,area,bin_type',
                'collectedBy:id,name',
                'segregationLog',
            ])
            ->orderBy('scheduled_time')
            ->get();

        return response()->json([
            'date' => $date,
            'collections' => $collections,
        ]);
    }
}
