<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * Register or refresh the authenticated user's push token.
     *
     * POST /api/v1/me/device-token
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'    => ['required', 'string', 'max:512'],
            'platform' => ['sometimes', 'string', 'in:android,ios,web'],
        ]);

        // Upsert: one row per token, linked to this user
        DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id'  => $request->user()->id,
                'platform' => $validated['platform'] ?? 'android',
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Remove the authenticated user's push token (on logout).
     *
     * DELETE /api/v1/me/device-token
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->delete();

        return response()->json(['success' => true]);
    }
}
