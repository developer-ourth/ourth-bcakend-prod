<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Campaign;
use App\Models\CitySettings;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AdminDashboardController - Admin Control Panel Dashboard
 *
 * Provides system-wide management: users, products, campaigns, cities, roles.
 */
class AdminDashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard/admin
     */
    public function index(Request $request): JsonResponse
    {
        $totalUsers = User::count();
        $usersByType = User::select('role', DB::raw('count(*) as count'))
            ->groupBy('role')
            ->pluck('count', 'role');

        $totalVendors = Vendor::count();
        $vendorsByKycStatus = Vendor::select('kyc_status', DB::raw('count(*) as count'))
            ->groupBy('kyc_status')
            ->pluck('count', 'kyc_status');

        $activeCampaigns = Campaign::where('status', 'active')->count();
        $activeCities = CitySettings::where('status', 'active')->count();
        $citiesByStatus = CitySettings::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $unresolvedAlerts = Alert::unresolved()->count();
        $criticalAlerts = Alert::unresolved()->critical()->get(['id', 'alert_type', 'title', 'city', 'created_at']);

        $recentUsers = User::latest()->limit(10)->get(['id', 'name', 'email', 'role', 'status', 'created_at']);
        $recentVendors = Vendor::with('user:id,name,email')
            ->latest()
            ->limit(10)
            ->get(['id', 'business_name', 'kyc_status', 'city', 'created_at', 'user_id']);

        return response()->json([
            'users' => [
                'total' => $totalUsers,
                'by_type' => $usersByType,
                'recent' => $recentUsers,
            ],
            'vendors' => [
                'total' => $totalVendors,
                'by_kyc_status' => $vendorsByKycStatus,
                'recent' => $recentVendors,
            ],
            'campaigns' => [
                'active' => $activeCampaigns,
            ],
            'cities' => [
                'active' => $activeCities,
                'by_status' => $citiesByStatus,
            ],
            'alerts' => [
                'unresolved' => $unresolvedAlerts,
                'critical' => $criticalAlerts,
            ],
        ]);
    }

    /**
     * GET /api/v1/dashboard/admin/users
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->has('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
            );
        }

        $users = $query->latest()->paginate(50);

        return response()->json($users);
    }

    /**
     * GET /api/v1/dashboard/admin/cities
     */
    public function cities(Request $request): JsonResponse
    {
        $cities = CitySettings::with('cityManager:id,name,email')
            ->orderBy('status')
            ->orderBy('city')
            ->get();

        return response()->json(['cities' => $cities]);
    }

    /**
     * GET /api/v1/dashboard/admin/campaigns
     */
    public function campaigns(Request $request): JsonResponse
    {
        $query = Campaign::with('createdBy:id,name');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $campaigns = $query->latest()->paginate(20);

        return response()->json($campaigns);
    }

    /**
     * GET /api/v1/dashboard/admin/alerts
     */
    public function alerts(Request $request): JsonResponse
    {
        $query = Alert::with('resolvedBy:id,name');

        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->boolean('unresolved', true)) {
            $query->unresolved();
        }

        $alerts = $query->orderByRaw("FIELD(severity,'critical','warning','info')")
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($alerts);
    }
}
