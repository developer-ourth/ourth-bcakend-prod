<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\User;
use App\Models\AttributionTouchpoint;
use App\Models\MetaCapiLog;
use App\Services\WhatsAppService;

class MarketingAttributionController extends Controller
{
    /**
     * Overview stats for Admin Marketing Attribution Dashboard
     */
    public function index()
    {
        $totalTouchpoints = AttributionTouchpoint::count();
        $totalCapiSynced = Order::where('capi_synced', true)->count();
        $totalCapiLogs = MetaCapiLog::where('status', 'SUCCESS')->count();

        // Revenue grouped by marketing campaign name
        $revenueByCampaign = DB::table('orders')
            ->join('attribution_touchpoints', 'orders.attributed_touchpoint_id', '=', 'attribution_touchpoints.id')
            ->select('attribution_touchpoints.campaign_name', DB::raw('COUNT(orders.id) as order_count'), DB::raw('SUM(orders.total_amount) as total_revenue'))
            ->groupBy('attribution_touchpoints.campaign_name')
            ->orderByDesc('total_revenue')
            ->get();

        $recentTouchpoints = AttributionTouchpoint::with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_touchpoints' => $totalTouchpoints,
                'total_capi_synced' => $totalCapiSynced,
                'total_capi_logs' => $totalCapiLogs,
                'revenue_by_campaign' => $revenueByCampaign,
                'recent_touchpoints' => $recentTouchpoints,
            ]
        ]);
    }

    /**
     * Send 1-Click WhatsApp Broadcast to Targeted Audience Segment
     */
    public function sendBroadcast(Request $request)
    {
        $validated = $request->validate([
            'segment' => 'nullable|string|in:all,b2b,b2c,abandoned_cart',
            'recipient_phone' => 'nullable|string',
            'message' => 'required|string|min:5',
            'media_type' => 'nullable|string|in:none,image,video',
            'media_url' => 'nullable|string',
            'cta_type' => 'nullable|string|in:none,shop_now,get_quote',
        ]);

        $wa = new WhatsAppService();
        $mediaType = $validated['media_type'] ?? 'none';
        $mediaUrl = $validated['media_url'] ?? null;
        $ctaType = $validated['cta_type'] ?? 'none';
        $sentCount = 0;

        if (!empty($validated['recipient_phone'])) {
            $phone = $validated['recipient_phone'];
            $user = User::where('phone', $phone)->first();
            $text = str_replace('{name}', $user?->name ?: 'there', $validated['message']);
            $text = str_replace('{phone}', $phone, $text);
            $text = str_replace('{business_name}', $user?->name ?: 'there', $text);

            $success = $wa->sendRichMediaMessage($phone, $text, $mediaType, $mediaUrl, $ctaType);
            if ($success) {
                $sentCount = 1;
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => "Meta Cloud API failed for {$phone}. Ensure token is saved in Website Settings and phone is added to Meta test list."
                ], 400);
            }
        } else {
            $segment = $validated['segment'] ?? 'all';
            $query = User::whereNotNull('phone');

            if ($segment === 'b2b') {
                $query->where('user_type', 'B2B_Distributor');
            } elseif ($segment === 'b2c') {
                $query->where('user_type', 'B2C');
            }

            $users = $query->get();
            foreach ($users as $user) {
                $text = str_replace('{name}', $user->name ?: 'valued customer', $validated['message']);
                $text = str_replace('{phone}', $user->phone ?: '', $text);
                $text = str_replace('{business_name}', $user->name ?: 'valued customer', $text);

                $success = $wa->sendRichMediaMessage($user->phone, $text, $mediaType, $mediaUrl, $ctaType);
                if ($success) {
                    $sentCount++;
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "WhatsApp Broadcast successfully dispatched to {$sentCount} contact(s)!",
            'sent_count' => $sentCount
        ]);
    }

    /**
     * Sales Team Lead Pipeline Endpoint
     */
    public function salesLeads()
    {
        $leads = User::whereIn('user_type', ['B2B_Distributor', 'Vendor'])
            ->orWhereHas('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $leads
        ]);
    }

    /**
     * Update Lead Status in Sales Pipeline
     */
    public function updateLeadStatus(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->update([
            'user_type' => $request->input('user_type', $user->user_type),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Lead updated successfully',
            'data' => $user
        ]);
    }
}
