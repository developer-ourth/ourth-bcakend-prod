<?php

namespace App\Http\Controllers\Api;

use App\Events\VendorApproved;
use App\Events\VendorRejected;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * KYCApprovalController - Admin KYC Approval Workflow
 *
 * Handles the approval and rejection of vendor KYC submissions.
 * This is a restricted endpoint for admin users only.
 *
 * Admin Workflow:
 * 1. List pending KYC approvals → index()
 * 2. Review vendor details and documents
 * 3. Approve → approve() → Updates kyc_status to 'verified'
 * 4. Or Reject → reject() → Updates kyc_status to 'rejected'
 */
class KYCApprovalController extends Controller
{
    /**
     * Get list of KYC approvals pending review
     *
     * GET /api/v1/kyc-approvals
     *
     * Query parameters:
     * - page=1
     * - per_page=15
     * - status=pending|under_review|verified|rejected
     *
     * Response:
     * {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Kumar Electronics",
     *       "email": "vendor@example.com",
     *       "kyc_status": "pending",
     *       "kyc_submitted_at": "2026-04-15T10:30:00Z",
     *       "documents": [...]
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "total": 45,
     *     "per_page": 15
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 15);
        $status = $request->query('status', null);

        $query = Vendor::select([
            'id',
            'user_id',
            'business_name',
            'gstin',
            'trade_license_number',
            'kyc_status',
            'kyc_verified_at',
            'kyc_verified_by',
        ])
            ->with(['kycDocuments', 'approval', 'user']);

        // Filter by specific status if provided, otherwise show all known statuses
        if ($status && in_array($status, ['pending', 'under_review', 'verified', 'rejected'])) {
            $query->where('kyc_status', $status);
        } else {
            $query->whereIn('kyc_status', ['pending', 'under_review', 'verified', 'rejected']);
        }

        $vendors = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Add approval_stage to each vendor for frontend
        $vendors->getCollection()->transform(function ($vendor) {
            $vendor->approval_stage = $vendor->approval?->approval_stage ?? 'pending';

            return $vendor;
        });

        return response()->json([
            'success' => true,
            'data' => $vendors->items(),
            'meta' => [
                'current_page' => $vendors->currentPage(),
                'total' => $vendors->total(),
                'per_page' => $vendors->perPage(),
                'last_page' => $vendors->lastPage(),
            ],
        ]);
    }

    /**
     * Get single KYC approval details
     *
     * GET /api/v1/kyc-approvals/{vendor}
     *
     * Returns full vendor details including all KYC documents
     */
    public function show(Vendor $vendor): JsonResponse
    {
        $vendor->load(['kycDocuments', 'approval', 'user']);

        // Progress step 2: mark as under_review the first time an admin views the application
        if ($vendor->approval && $vendor->approval->approval_stage === 'pending_documents') {
            $vendor->approval->update(['approval_stage' => 'under_review']);
            $vendor->approval->refresh();
        }

        $vendor->approval_stage = $vendor->approval?->approval_stage ?? 'pending_documents';

        return response()->json([
            'success' => true,
            'data' => $vendor,
        ]);
    }

    /**
     * Approve vendor KYC
     *
     * POST /api/v1/kyc-approvals/{vendor}/approve
     *
     * Request:
     * {
     *   "notes": "All documents verified and authentic"
     * }
     *
     * Effects:
     * - Sets kyc_status = 'verified'
     * - Sets kyc_verified_at = now()
     * - Sets kyc_verified_by = auth()->user()->id
     * - Updates vendor_approvals table with approval_stage = 'approved'
     *
     * Response:
     * {
     *   "success": true,
     *   "message": "KYC approved successfully",
     *   "data": {
     *     "id": 1,
     *     "kyc_status": "verified",
     *     "kyc_verified_at": "2026-04-20T14:30:00Z",
     *     ...
     *   }
     * }
     */
    public function approve(Request $request, Vendor $vendor): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $admin = auth()->user();

            // Update vendor KYC status
            $vendor->update([
                'kyc_status' => 'verified',
                'kyc_verified_at' => now(),
                'kyc_verified_by' => $admin->id,
            ]);

            // Update or create vendor approval record
            $vendor->approval()->updateOrCreate(
                ['vendor_id' => $vendor->id],
                [
                    'approval_stage' => 'approved',
                    'approved_by' => $admin->id,
                    'approved_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            // Dispatch event for notifications/jobs
            event(new VendorApproved($vendor));

            // Refresh and reload relationships
            $vendor->refresh();
            $vendor->load('approval');
            $vendor->approval_stage = $vendor->approval?->approval_stage ?? 'pending';

            return response()->json([
                'success' => true,
                'message' => 'KYC approved successfully',
                'data' => $vendor,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Approval failed: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reject vendor KYC
     *
     * POST /api/v1/kyc-approvals/{vendor}/reject
     *
     * Request:
     * {
     *   "reason": "GST certificate not authentic",
     *   "notes": "Please resubmit with original documents"
     * }
     *
     * Effects:
     * - Sets kyc_status = 'rejected'
     * - Updates vendor_approvals table with approval_stage = 'rejected'
     * - Stores rejection reason for vendor communication
     *
     * Response:
     * {
     *   "success": true,
     *   "message": "KYC rejected",
     *   "data": {...}
     * }
     */
    public function reject(Request $request, Vendor $vendor): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $admin = auth()->user();

            // Update vendor KYC status
            $vendor->update([
                'kyc_status' => 'rejected',
            ]);

            // Update or create vendor approval record with rejection
            $vendor->approval()->updateOrCreate(
                ['vendor_id' => $vendor->id],
                [
                    'approval_stage' => 'rejected',
                    'rejected_by' => $admin->id,
                    'rejected_at' => now(),
                    'rejection_reason' => $validated['reason'],
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            // Dispatch event for notifications
            event(new VendorRejected($vendor));

            // Refresh and reload
            $vendor->refresh();
            $vendor->load('approval');
            $vendor->approval_stage = $vendor->approval?->approval_stage ?? 'pending';

            return response()->json([
                'success' => true,
                'message' => 'KYC rejected',
                'data' => $vendor,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Rejection failed: '.$e->getMessage(),
            ], 400);
        }
    }
}
