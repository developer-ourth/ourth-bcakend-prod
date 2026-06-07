<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AddressController
 *
 * Manages the authenticated consumer's saved addresses.
 */
class AddressController extends Controller
{
    /**
     * List all addresses for the authenticated user.
     *
     * GET /api/v1/me/addresses
     */
    public function index(Request $request): JsonResponse
    {
        $addresses = Address::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $addresses]);
    }

    /**
     * Create a new address.
     *
     * POST /api/v1/me/addresses
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'address_line1' => ['required', 'string', 'max:500'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'mobile'        => ['nullable', 'string', 'max:20'],
            'is_default'    => ['boolean'],
            'is_billing'    => ['boolean'],
        ]);

        $user = $request->user();

        if (! empty($validated['is_default'])) {
            Address::where('user_id', $user->id)->update(['is_default' => false]);
        }

        if (! empty($validated['is_billing'])) {
            Address::where('user_id', $user->id)->update(['is_billing' => false]);
        }

        $address = Address::create(array_merge($validated, ['user_id' => $user->id]));

        return response()->json(['success' => true, 'data' => $address], 201);
    }

    /**
     * Update an existing address.
     *
     * PATCH /api/v1/me/addresses/{address}
     */
    public function update(Request $request, Address $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'address_line1' => ['sometimes', 'string', 'max:500'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'max:100'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'mobile'        => ['nullable', 'string', 'max:20'],
            'is_default'    => ['boolean'],
            'is_billing'    => ['boolean'],
        ]);

        if (! empty($validated['is_default'])) {
            Address::where('user_id', $request->user()->id)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        if (! empty($validated['is_billing'])) {
            Address::where('user_id', $request->user()->id)
                ->where('id', '!=', $address->id)
                ->update(['is_billing' => false]);
        }

        $address->update($validated);

        return response()->json(['success' => true, 'data' => $address]);
    }

    /**
     * Delete an address.
     *
     * DELETE /api/v1/me/addresses/{address}
     */
    public function destroy(Request $request, Address $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $address->delete();

        return response()->json(['success' => true, 'message' => 'Address deleted.']);
    }
}
