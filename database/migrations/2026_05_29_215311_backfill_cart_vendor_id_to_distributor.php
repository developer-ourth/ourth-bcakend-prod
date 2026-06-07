<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Update any active carts that have a null or stale vendor_id to point to
     * the Ourth distributor vendor, so existing carts can accept new items.
     */
    public function up(): void
    {
        $distributorId = DB::table('vendors')->where('is_distributor', true)->value('id');

        if (! $distributorId) {
            return;
        }

        DB::table('carts')
            ->where('status', 'active')
            ->where(function ($q) use ($distributorId) {
                $q->whereNull('vendor_id')
                    ->orWhere('vendor_id', '!=', $distributorId);
            })
            ->update(['vendor_id' => $distributorId]);
    }

    public function down(): void
    {
        // Data migrations are not reversed.
    }
};
