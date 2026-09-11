<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consumer\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProfileController
 *
 * Authenticated consumer profile read and update.
 */
class ProfileController extends Controller
{
    /**
     * Get the authenticated consumer's profile.
     *
     * GET /api/v1/me/profile
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'role'          => $user->role,
                'status'        => $user->status,
                'gstin'         => $user->gstin ?? $user->vendor?->gstin,
                'business_name' => $user->vendor?->business_name,
                'vendor_id'     => $user->vendor_id,
                'created_at'    => $user->created_at,
            ],
        ]);
    }

    /**
     * Update the authenticated consumer's profile.
     *
     * PATCH /api/v1/me/profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data'    => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'role'          => $user->role,
                'status'        => $user->status,
                'gstin'         => $user->gstin ?? $user->vendor?->gstin,
                'business_name' => $user->vendor?->business_name,
                'vendor_id'     => $user->vendor_id,
                'created_at'    => $user->created_at,
            ],
        ]);
    }

    /**
     * Delete the authenticated consumer's account.
     *
     * DELETE /api/v1/me/profile or DELETE /api/v1/me/account
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke all tokens
        if ($user->tokens()) {
            $user->tokens()->delete();
        }

        // Soft delete user account
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully.',
        ]);
    }
}
