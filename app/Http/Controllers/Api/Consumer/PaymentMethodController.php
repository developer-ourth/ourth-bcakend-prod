<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    /** GET /api/v1/me/payment-methods */
    public function index(Request $request): JsonResponse
    {
        $methods = PaymentMethod::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $methods]);
    }

    /** POST /api/v1/me/payment-methods */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'       => ['required', 'in:upi,card,netbanking,wallet,cod'],
            'provider'   => ['nullable', 'string', 'max:100'],
            'identifier' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ]);

        $user = $request->user();

        if (! empty($validated['is_default'])) {
            PaymentMethod::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $method = PaymentMethod::create(array_merge($validated, ['user_id' => $user->id]));

        return response()->json(['success' => true, 'data' => $method], 201);
    }

    /** PATCH /api/v1/me/payment-methods/{method} */
    public function update(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        if ($paymentMethod->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'type'       => ['sometimes', 'in:upi,card,netbanking,wallet,cod'],
            'provider'   => ['nullable', 'string', 'max:100'],
            'identifier' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ]);

        if (! empty($validated['is_default'])) {
            PaymentMethod::where('user_id', $request->user()->id)
                ->where('id', '!=', $paymentMethod->id)
                ->update(['is_default' => false]);
        }

        $paymentMethod->update($validated);

        return response()->json(['success' => true, 'data' => $paymentMethod->fresh()]);
    }

    /** DELETE /api/v1/me/payment-methods/{method} */
    public function destroy(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        if ($paymentMethod->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $paymentMethod->delete();

        return response()->json(['success' => true, 'message' => 'Payment method removed.']);
    }
}
