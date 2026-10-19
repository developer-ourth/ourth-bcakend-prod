<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppBroadcastMessage;
use App\Models\AttributionTouchpoint;
use App\Models\MetaCapiLog;
use App\Models\Order;
use App\Models\User;
use App\Services\KrayinClient;
use App\Services\LeadCaptureService;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MarketingAttributionController extends Controller
{
    /**
     * Overview stats for the Marketing dashboards
     */
    public function index()
    {
        $revenueByCampaign = DB::table('orders')
            ->join('attribution_touchpoints', 'orders.attributed_touchpoint_id', '=', 'attribution_touchpoints.id')
            ->where('orders.payment_status', 'paid')
            ->select(
                DB::raw("COALESCE(attribution_touchpoints.campaign_name, attribution_touchpoints.source_type) as campaign_name"),
                DB::raw('COUNT(orders.id) as order_count'),
                DB::raw('SUM(orders.total_amount) as total_revenue')
            )
            ->groupBy(DB::raw("COALESCE(attribution_touchpoints.campaign_name, attribution_touchpoints.source_type)"))
            ->orderByDesc('total_revenue')
            ->get();

        $leadsBySource = AttributionTouchpoint::select('source_type', DB::raw('COUNT(*) as total'))
            ->groupBy('source_type')
            ->pluck('total', 'source_type');

        $recentTouchpoints = AttributionTouchpoint::with('user:id,name,phone')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_touchpoints' => AttributionTouchpoint::count(),
                'total_leads' => User::whereNotNull('crm_segment')->count(),
                'leads_this_month' => User::whereNotNull('crm_segment')->where('created_at', '>=', now()->startOfMonth())->count(),
                'paid_orders_this_month' => Order::where('payment_status', 'paid')->where('created_at', '>=', now()->startOfMonth())->count(),
                'total_capi_synced' => Order::where('capi_synced', true)->count(),
                'total_capi_logs' => MetaCapiLog::where('status', 'SUCCESS')->count(),
                'leads_by_source' => $leadsBySource,
                'revenue_by_campaign' => $revenueByCampaign,
                'recent_touchpoints' => $recentTouchpoints,
                'crm_url' => config('services.krayin.url') ?: null,
                'inbox_url' => config('services.chatwoot.url') ?: null,
            ],
        ]);
    }

    /**
     * Queue a WhatsApp broadcast to one number or an audience segment
     */
    public function sendBroadcast(Request $request)
    {
        $validated = $request->validate([
            'segment' => 'nullable|string|in:all,b2b,b2c,abandoned_cart',
            'recipient_phone' => 'nullable|string',
            'message' => 'required|string|min:5|max:1024',
            'media_type' => 'nullable|string|in:none,image,video',
            'media_url' => 'nullable|url|required_if:media_type,image,video',
            'cta_type' => 'nullable|string|in:none,shop_now,get_quote',
            'template_name' => ['nullable', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'template_language' => 'nullable|string|max:10',
        ]);

        if (!empty($validated['recipient_phone'])) {
            $phone = PhoneNumber::normalize($validated['recipient_phone']);

            if (!$phone) {
                return response()->json(['status' => 'error', 'message' => 'Invalid phone number.'], 422);
            }

            $recipients = collect([[
                'phone' => $phone,
                'name' => PhoneNumber::findUser($phone)?->name ?: 'there',
            ]]);
        } else {
            $recipients = $this->segmentQuery($validated['segment'] ?? 'all')
                ->get(['id', 'name', 'phone'])
                ->map(fn (User $user) => [
                    'phone' => PhoneNumber::normalize($user->phone),
                    'name' => $user->name ?: 'valued customer',
                ])
                ->filter(fn ($recipient) => $recipient['phone'])
                ->unique('phone');
        }

        foreach ($recipients as $recipient) {
            SendWhatsAppBroadcastMessage::dispatch(
                $recipient['phone'],
                $recipient['name'],
                $validated['message'],
                $validated['media_type'] ?? 'none',
                $validated['media_url'] ?? null,
                $validated['cta_type'] ?? 'none',
                $validated['template_name'] ?? null,
                $validated['template_language'] ?? config('services.meta.template_language', 'en'),
            );
        }

        $count = $recipients->count();

        return response()->json([
            'status' => 'success',
            'message' => "WhatsApp broadcast queued for {$count} contact(s). Delivery failures are logged.",
            'queued_count' => $count,
        ]);
    }

    /**
     * Leads list for the Sales dashboard (the full pipeline lives in Krayin CRM)
     */
    public function salesLeads(Request $request, KrayinClient $krayin)
    {
        $leads = User::query()
            ->where(fn ($query) => $query->whereNotNull('crm_segment')->orWhereHas('orders'))
            ->when($request->query('segment'), fn ($query, $segment) => $query->where('crm_segment', $segment))
            ->orderByDesc('created_at')
            ->paginate(20, ['id', 'name', 'phone', 'email', 'crm_segment', 'krayin_lead_id', 'created_at']);

        $leads->getCollection()->transform(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'phone_number' => $user->phone,
            'email' => $user->email,
            'segment' => $user->crm_segment ?? 'B2C',
            'crm_lead_url' => $user->krayin_lead_id && $krayin->isConfigured() ? $krayin->leadUrl($user->krayin_lead_id) : null,
            'created_at' => $user->created_at,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $leads,
            'counts' => User::whereNotNull('crm_segment')
                ->select('crm_segment', DB::raw('COUNT(*) as total'))
                ->groupBy('crm_segment')
                ->pluck('total', 'crm_segment'),
        ]);
    }

    /**
     * Change a lead's segment (B2C / B2B wholesale / vendor partner)
     */
    public function updateLeadStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'segment' => ['required', Rule::in(LeadCaptureService::SEGMENTS)],
        ]);

        $user = User::findOrFail($id);
        $user->update(['crm_segment' => $validated['segment']]);

        return response()->json([
            'status' => 'success',
            'message' => 'Lead updated successfully',
            'data' => ['id' => $user->id, 'segment' => $user->crm_segment],
        ]);
    }

    private function segmentQuery(string $segment)
    {
        $query = User::query()->whereNotNull('phone')->where('phone', '!=', '');

        return match ($segment) {
            'b2b' => $query->where('crm_segment', 'B2B_Distributor'),
            'b2c' => $query->where(fn ($q) => $q->where('crm_segment', 'B2C')->orWhere('role', 'consumer')),
            'abandoned_cart' => $query
                ->whereHas('carts', fn ($q) => $q->whereHas('items')->where('updated_at', '>=', now()->subDays(7)))
                ->whereDoesntHave('orders', fn ($q) => $q->where('payment_status', 'paid')->where('created_at', '>=', now()->subDays(7))),
            default => $query,
        };
    }
}
