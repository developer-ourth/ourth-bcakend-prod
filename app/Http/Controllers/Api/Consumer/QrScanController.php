<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consumer\QrScanRequest;
use App\Models\QrScanLog;
use App\Models\RewardTransaction;
use App\Models\VendorQrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * QrScanController
 *
 * Handles consumer QR code scans at vendor locations.
 * Awards loyalty points on each verified scan.
 */
class QrScanController extends Controller
{
    /** Points awarded per QR scan */
    private const SCAN_POINTS = 10;

    /** Hours required between repeat scans at the same vendor to prevent abuse */
    private const SCAN_COOLDOWN_HOURS = 6;

    /**
     * Process a QR code scan by the consumer.
     *
     * POST /api/v1/me/qr/scan
     */
    public function scan(QrScanRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user      = $request->user();

        $qrCode = VendorQrCode::where('qr_code_id', $validated['qr_code_id'])
            ->where('status', 'active')
            ->with('vendor:id,business_name')
            ->first();

        if (! $qrCode) {
            return response()->json(['success' => false, 'message' => 'Invalid or inactive QR code.'], 404);
        }

        // Cooldown: prevent abuse by the same user scanning the same vendor repeatedly
        $recentScan = QrScanLog::where('vendor_qr_code_id', $qrCode->id)
            ->where('scanned_by', $user->id)
            ->where('created_at', '>=', now()->subHours(self::SCAN_COOLDOWN_HOURS))
            ->exists();

        if ($recentScan) {
            return response()->json([
                'success' => false,
                'message' => 'You have already scanned this vendor recently. Try again later.',
            ], 429);
        }

        $transaction = DB::transaction(function () use ($user, $qrCode, $validated) {
            // Log the scan
            QrScanLog::create([
                'vendor_qr_code_id'   => $qrCode->id,
                'scan_context'        => 'loyalty',
                'scanned_by'          => $user->id,
                'ip_address'          => request()->ip(),
                'user_agent'          => request()->userAgent(),
                'latitude'            => $validated['latitude'] ?? null,
                'longitude'           => $validated['longitude'] ?? null,
            ]);

            // Update QR scan stats
            $qrCode->increment('scans_count');
            $qrCode->update(['last_scanned_at' => now()]);

            // Credit loyalty points
            $currentBalance = RewardTransaction::where('user_id', $user->id)
                ->orderByDesc('id')
                ->value('points_balance_after') ?? 0;

            $newBalance = $currentBalance + self::SCAN_POINTS;

            return RewardTransaction::create([
                'user_id'              => $user->id,
                'transaction_type'     => 'credit',
                'points'               => self::SCAN_POINTS,
                'points_balance_after' => $newBalance,
                'source'               => 'qr_scan',
                'source_reference'     => $qrCode->qr_code_id,
                'description'          => "QR scan at {$qrCode->vendor->business_name}",
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => "You earned ".self::SCAN_POINTS." points!",
            'data'    => [
                'points_earned'  => self::SCAN_POINTS,
                'new_balance'    => $transaction->points_balance_after,
                'vendor_name'    => $qrCode->vendor->business_name,
            ],
        ]);
    }
}
