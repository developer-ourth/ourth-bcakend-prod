<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminCouponController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $coupons = Coupon::with('product')->orderBy('created_at', 'desc')->get();
        return response()->json($coupons);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:coupons,code|max:255',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'product_id' => 'nullable|exists:products,id',
            'expires_at' => 'nullable|date',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $coupon = Coupon::create($validated);
        
        return response()->json($coupon->load('product'), Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $coupon = Coupon::with('product')->findOrFail($id);
        return response()->json($coupon);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $coupon = Coupon::findOrFail($id);
        
        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:coupons,code,'.$id,
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'product_id' => 'nullable|exists:products,id',
            'expires_at' => 'nullable|date',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $coupon->update($validated);

        return response()->json($coupon->load('product'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
