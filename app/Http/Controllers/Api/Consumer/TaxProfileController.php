<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Http\Controllers\Controller;
use App\Models\UserTaxProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxProfileController extends Controller
{
    /** GET /api/v1/me/tax-profile */
    public function show(Request $request): JsonResponse
    {
        $profile = UserTaxProfile::firstOrNew(['user_id' => $request->user()->id]);

        return response()->json(['success' => true, 'data' => $profile->exists ? $profile : null]);
    }

    /** PUT /api/v1/me/tax-profile */
    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_gst_registered'    => ['required', 'boolean'],
            'gstin'                => ['nullable', 'string', 'max:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
            'legal_business_name'  => ['nullable', 'string', 'max:255'],
        ]);

        // Require GSTIN when GST-registered
        if ($validated['is_gst_registered'] && empty($validated['gstin'])) {
            return response()->json([
                'success' => false,
                'message' => 'GSTIN is required when GST registered is enabled.',
            ], 422);
        }

        $profile = UserTaxProfile::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated,
        );

        return response()->json(['success' => true, 'data' => $profile]);
    }
}
