<?php

namespace App\Http\Controllers\Api;

use App\Contracts\KYCProcessorContract;
use App\Contracts\QRCodeGeneratorContract;
use App\Events\KYCDocumentSubmitted;
use App\Http\Controllers\Controller;
use App\Mail\VendorWelcomeEmail;
use App\Models\Vendor;
use App\Models\VendorKycDocument;
use App\Services\VendorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * VendorController - Vendor Onboarding & KYC Management
 *
 * This controller uses service classes for business logic, making it easy
 * to extract functionality to microservices in Phase 2.
 *
 * Workflow:
 * 1. Register → VendorService.register() → VendorRegistered event
 * 2. Upload KYC → Direct DB insert → KYCDocumentSubmitted event
 * 3. Admin Approves → VendorService.approve() → VendorApproved event
 *    → GenerateQRCodeJob → SendNotificationJob
 * 4. Get QR Code → QRCodeService.getQRCode()
 */
class VendorController extends Controller
{
    public function __construct(
        private VendorService $vendorService,
        private QRCodeGeneratorContract $qrCodeService,
        private KYCProcessorContract $kycService,
    ) {}

    /**
     * Register a new vendor
     *
     * POST /api/v1/vendors/register
     *
     * Request:
     * {
     *   "name": "John Doe",
     *   "email": "vendor@example.com",
     *   "phone": "+919876543210",
     *   "password": "secure_password",
     *   "business_name": "Kumar Electronics",
     *   "gstin": "27AABCT1234H1Z0",
     *   "trade_license_number": "TL/123/456",
     *   "address_line1": "123 Main St",
     *   "city": "Mumbai",
     *   "state": "Maharashtra",
     *   "postal_code": "400001"
     * }
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:6',
            'business_name' => 'required|string|max:255',
            'gstin' => ['required', 'string', 'max:15', 'unique:vendors', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'trade_license_number' => 'nullable|string',
            'address_line1' => 'nullable|string',
            'postal_code' => 'nullable|string|max:10',
        ]);

        try {
            $result = $this->vendorService->register($validated);

            return response()->json([
                'success' => true,
                'message' => 'Vendor registered successfully. Your application is under review.',
                'data' => [
                    'vendor_id' => $result['vendor']->id,
                    'vendor_code' => $result['vendor']->vendor_code,
                    'user' => $result['user'],
                    'vendor' => $result['vendor'],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Upload KYC document
     *
     * POST /api/v1/vendors/kyc/upload
     *
     * Request:
     * {
     *   "vendor_id": 1,
     *   "document_type": "gst_certificate",
     *   "document_url": "https://s3.amazonaws.com/kyc/..."
     * }
     */
    public function uploadKyc(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'document_type' => 'required|string|in:gst_certificate,trade_license,pan_card,aadhar,bank_statement',
            'document_url' => 'required|url',
        ]);

        try {
            $kyc = VendorKycDocument::create([
                'vendor_id' => $validated['vendor_id'],
                'document_type' => $validated['document_type'],
                'document_url' => $validated['document_url'],
                'status' => 'submitted',
            ]);

            // Dispatch event for async processing
            event(new KYCDocumentSubmitted($kyc));

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => $kyc,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get vendor approval status
     *
     * GET /api/v1/vendors/{vendor}/approval-status
     */
    public function approvalStatus(Vendor $vendor): JsonResponse
    {
        $status = $this->vendorService->getApprovalStatus($vendor);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Get QR code for vendor
     *
     * GET /api/v1/vendors/{vendor}/qr
     *
     * Returns QR code only if vendor is approved
     */
    public function getQrCode(Vendor $vendor): JsonResponse
    {
        if ($vendor->kyc_status !== 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not approved yet. QR code unavailable.',
            ], 403);
        }

        $qrCode = $this->qrCodeService->getQRCode($vendor);

        if (! $qrCode) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not generated yet. Please contact support.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $qrCode,
        ]);
    }

    /**
     * Approve vendor (Admin only)
     *
     * POST /api/v1/vendors/{vendor}/approve
     *
     * Request:
     * {
     *   "notes": "All documents verified"
     * }
     */
    public function approve(Request $request, Vendor $vendor): JsonResponse
    {
        // TODO: Add auth middleware to check if user is admin
        // $this->authorize('approve', Vendor::class);

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        try {
            $admin = auth()->user(); // Get authenticated admin user

            $this->vendorService->approve(
                $vendor,
                $admin,
                $validated['notes'] ?? ''
            );

            // Refresh vendor from database to get updated relationships
            $vendor->refresh();
            $vendor->load('approval');

            // Add approval_stage to vendor at top level for frontend
            $vendor->approval_stage = $vendor->approval?->approval_stage ?? 'pending';

            return response()->json([
                'success' => true,
                'message' => 'Vendor approved successfully',
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
     * Reject vendor (Admin only)
     *
     * POST /api/v1/vendors/{vendor}/reject
     *
     * Request:
     * {
     *   "reason": "Invalid GST certificate"
     * }
     */
    public function reject(Request $request, Vendor $vendor): JsonResponse
    {
        // TODO: Add auth middleware
        // $this->authorize('reject', Vendor::class);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $admin = auth()->user();

            $this->vendorService->reject($vendor, $admin, $validated['reason']);

            // Refresh vendor from database to get updated relationships
            $vendor->refresh();
            $vendor->load('approval');

            // Add approval_stage to vendor at top level for frontend
            $vendor->approval_stage = $vendor->approval?->approval_stage ?? 'pending';

            return response()->json([
                'success' => true,
                'message' => 'Vendor rejected successfully',
                'data' => $vendor,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Rejection failed: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get vendor with all details
     *
     * GET /api/v1/vendors/{vendor}
     */
    public function show(Vendor $vendor): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->vendorService->getWithRelations($vendor),
        ]);
    }

    /**
     * List all vendors (with pagination)
     *
     * GET /api/v1/vendors?page=1&limit=10
     */
    public function index(Request $request): JsonResponse
    {
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 10);

        $vendors = Vendor::with(['user', 'approval', 'activeQrCode'])
            ->paginate($limit, ['*'], 'page', $page);

        // Format vendors to include approval_stage at top level
        $formattedVendors = $vendors->items();
        foreach ($formattedVendors as $vendor) {
            $vendor->approval_stage = $vendor->approval?->approval_stage ?? 'pending';
        }

        return response()->json([
            'success' => true,
            'data' => $formattedVendors,
            'pagination' => [
                'total' => $vendors->total(),
                'per_page' => $vendors->perPage(),
                'current_page' => $vendors->currentPage(),
                'last_page' => $vendors->lastPage(),
            ],
        ]);
    }

    /**
     * Resend welcome email containing the vendor's login ID.
     *
     * POST /api/v1/vendors/resend-vendor-id-email
     *
     * Requires: auth:sanctum (vendor must be logged in)
     * Rate-limit: use the throttle middleware on the route.
     */
    public function resendVendorIdEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        $vendor = Vendor::where('user_id', $user->id)->first();

        if (! $vendor) {
            return response()->json([
                'success' => false,
                'message' => 'No vendor profile found for this account.',
            ], 404);
        }

        Mail::to($user->email)->queue(new VendorWelcomeEmail($vendor, $user));

        return response()->json([
            'success' => true,
            'message' => 'Your Vendor ID has been sent to '.$user->email,
        ]);
    }
}
