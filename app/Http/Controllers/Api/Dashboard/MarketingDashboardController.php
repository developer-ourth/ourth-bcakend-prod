<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Referral;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * MarketingDashboardController - Marketing & Growth Dashboard
 *
 * Tracks campaigns, vendor acquisition funnel, consumer retention, referrals, geo growth.
 */
class MarketingDashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard/marketing
     */
    public function index(Request $request): JsonResponse
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $today = now()->toDateString();
        $lastMonthStart = now()->subMonth()->startOfMonth()->toDateString();
        $lastMonthEnd = now()->subMonth()->endOfMonth()->toDateString();

        $activeCampaigns = Campaign::where('status', 'active')
            ->get(['id', 'name', 'type', 'target_audience', 'budget', 'amount_spent', 'impressions', 'clicks', 'conversions', 'start_date', 'end_date']);

        $campaignSummary = Campaign::where('status', 'active')
            ->selectRaw('SUM(impressions) as total_impressions, SUM(clicks) as total_clicks, SUM(conversions) as total_conversions, SUM(amount_spent) as total_spent')
            ->first();

        $newVendorsThisMonth = Vendor::whereBetween('created_at', [$monthStart.' 00:00:00', now()])->count();
        $newVendorsLastMonth = Vendor::whereBetween('created_at', [$lastMonthStart.' 00:00:00', $lastMonthEnd.' 23:59:59'])->count();
        $pendingKycVendors = Vendor::where('kyc_status', 'pending')->count();
        $approvedVendors = Vendor::where('kyc_status', 'verified')->count();

        $newConsumersThisMonth = User::where('user_type', 'consumer')
            ->whereBetween('created_at', [$monthStart.' 00:00:00', now()])
            ->count();

        $newConsumersLastMonth = User::where('user_type', 'consumer')
            ->whereBetween('created_at', [$lastMonthStart.' 00:00:00', $lastMonthEnd.' 23:59:59'])
            ->count();

        $referralStats = Referral::whereBetween('created_at', [$monthStart.' 00:00:00', now()])
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $totalReferrals = Referral::whereBetween('created_at', [$monthStart.' 00:00:00', now()])->count();
        $convertedReferrals = Referral::whereIn('status', ['activated', 'rewarded'])
            ->whereBetween('created_at', [$monthStart.' 00:00:00', now()])
            ->count();

        $vendorGrowthByCity = Vendor::whereBetween('created_at', [$monthStart.' 00:00:00', now()])
            ->select('city', DB::raw('count(*) as new_vendors'))
            ->groupBy('city')
            ->orderByDesc('new_vendors')
            ->get();

        $consumerGrowthByCity = User::where('user_type', 'consumer')
            ->whereBetween('created_at', [$monthStart.' 00:00:00', now()])
            ->whereNotNull('last_ip_address')
            ->select(DB::raw('count(*) as new_consumers'))
            ->first();

        $vendorAcquisitionFunnel = [
            'registered' => Vendor::count(),
            'kyc_submitted' => Vendor::whereNotIn('kyc_status', ['not_submitted'])->count(),
            'kyc_approved' => Vendor::where('kyc_status', 'verified')->count(),
            'first_order_placed' => Vendor::where('total_orders', '>', 0)->count(),
            'active_30d' => Vendor::whereHas('orders', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)))->count(),
        ];

        $topCampaignsByConversion = Campaign::orderByDesc('conversions')
            ->limit(5)
            ->get(['id', 'name', 'type', 'impressions', 'clicks', 'conversions', 'amount_spent']);

        return response()->json([
            'campaign_summary' => [
                'active_campaigns' => $activeCampaigns->count(),
                'total_impressions' => $campaignSummary?->total_impressions ?? 0,
                'total_clicks' => $campaignSummary?->total_clicks ?? 0,
                'total_conversions' => $campaignSummary?->total_conversions ?? 0,
                'total_spent' => $campaignSummary?->total_spent ?? 0,
                'ctr_percent' => ($campaignSummary?->total_impressions ?? 0) > 0
                    ? round(($campaignSummary->total_clicks / $campaignSummary->total_impressions) * 100, 2)
                    : 0,
            ],
            'active_campaigns' => $activeCampaigns,
            'top_campaigns_by_conversion' => $topCampaignsByConversion,
            'vendor_acquisition' => [
                'new_this_month' => $newVendorsThisMonth,
                'new_last_month' => $newVendorsLastMonth,
                'mom_growth' => $newVendorsLastMonth > 0
                    ? round((($newVendorsThisMonth - $newVendorsLastMonth) / $newVendorsLastMonth) * 100, 2)
                    : null,
                'funnel' => $vendorAcquisitionFunnel,
                'by_city' => $vendorGrowthByCity,
            ],
            'consumer_acquisition' => [
                'new_this_month' => $newConsumersThisMonth,
                'new_last_month' => $newConsumersLastMonth,
                'mom_growth' => $newConsumersLastMonth > 0
                    ? round((($newConsumersThisMonth - $newConsumersLastMonth) / $newConsumersLastMonth) * 100, 2)
                    : null,
            ],
            'referrals' => [
                'total_this_month' => $totalReferrals,
                'converted_this_month' => $convertedReferrals,
                'conversion_rate_percent' => $totalReferrals > 0
                    ? round(($convertedReferrals / $totalReferrals) * 100, 2)
                    : 0,
                'by_status' => $referralStats,
            ],
        ]);
    }

    /**
     * GET /api/v1/dashboard/marketing/campaigns/{campaign}
     *
     * Returns detailed performance data for a single campaign.
     */
    public function campaignDetail(Request $request, Campaign $campaign): JsonResponse
    {
        $ctr = $campaign->impressions > 0
            ? round(($campaign->clicks / $campaign->impressions) * 100, 2)
            : 0;

        $conversionRate = $campaign->clicks > 0
            ? round(($campaign->conversions / $campaign->clicks) * 100, 2)
            : 0;

        $costPerConversion = $campaign->conversions > 0
            ? round($campaign->amount_spent / $campaign->conversions, 2)
            : null;

        $referrals = Referral::where('campaign_id', $campaign->id)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'campaign' => $campaign,
            'performance' => [
                'ctr_percent' => $ctr,
                'conversion_rate_percent' => $conversionRate,
                'cost_per_conversion' => $costPerConversion,
                'roi_percent' => $campaign->amount_spent > 0 && $costPerConversion
                    ? round((($campaign->amount_spent - ($costPerConversion * $campaign->conversions)) / $campaign->amount_spent) * 100, 2)
                    : null,
            ],
            'referrals_by_status' => $referrals,
        ]);
    }
}
