<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Ensure the Ourth distributor vendor exists and all orphaned products are linked to it.
     * This migration is idempotent — safe to run multiple times.
     */
    public function up(): void
    {
        // 1. Ensure the distributor user exists.
        $user = DB::table('users')->where('email', 'distributor@ourth.app')->first();

        if (! $user) {
            $userId = DB::table('users')->insertGetId([
                'name' => 'Ourth',
                'email' => 'distributor@ourth.app',
                'password' => Hash::make(Str::random(32)),
                'user_type' => 'vendor',
                'role' => 'vendor',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $userId = $user->id;
        }

        // 2. Ensure the distributor vendor exists and is marked verified + is_distributor.
        $vendor = DB::table('vendors')->where('user_id', $userId)->first();

        if (! $vendor) {
            $insertData = [
                'user_id' => $userId,
                'business_name' => 'Ourth',
                'kyc_status' => 'verified',
                'is_distributor' => true,
                'country' => 'India',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Only set vendor_code if the value is not already taken.
            if (! DB::table('vendors')->where('vendor_code', 'OURTH')->exists()) {
                $insertData['vendor_code'] = 'OURTH';
            }

            DB::table('vendors')->insert($insertData);
        } else {
            DB::table('vendors')->where('id', $vendor->id)->update([
                'is_distributor' => true,
                'kyc_status' => 'verified',
                'updated_at' => now(),
            ]);
        }

        // 3. Backfill any products that still have a null vendor_id.
        $distributorId = DB::table('vendors')->where('is_distributor', true)->value('id');

        if ($distributorId) {
            DB::table('products')
                ->whereNull('vendor_id')
                ->whereNull('deleted_at')
                ->update(['vendor_id' => $distributorId]);
        }
    }

    public function down(): void
    {
        // Data migrations are not reversed.
    }
};
