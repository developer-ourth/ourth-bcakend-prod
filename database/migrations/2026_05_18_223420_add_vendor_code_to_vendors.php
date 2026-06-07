<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('vendor_code', 10)->unique()->nullable()->after('id');
        });

        // Back-fill existing vendors with a unique 6-digit code
        DB::table('vendors')->whereNull('vendor_code')->orderBy('id')->each(function ($vendor) {
            do {
                $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            } while (DB::table('vendors')->where('vendor_code', $code)->exists());
            DB::table('vendors')->where('id', $vendor->id)->update(['vendor_code' => $code]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('vendor_code');
        });
    }
};
