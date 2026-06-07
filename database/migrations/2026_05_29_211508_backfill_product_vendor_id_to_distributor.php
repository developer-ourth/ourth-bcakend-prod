<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Assign any products with no vendor to the Ourth distributor.
        // Products created through the admin dashboard before the DistributorSeeder
        // ran will have vendor_id = null and are invisible to the marketplace query
        // that requires a verified vendor.
        DB::statement('
            UPDATE products
            SET vendor_id = (
                SELECT id FROM vendors WHERE is_distributor = true LIMIT 1
            )
            WHERE vendor_id IS NULL
              AND deleted_at IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible — we cannot know which products originally had null vendor_id
    }
};
