<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Tracks which vendor placed this order (null for future B2C consumers)
            $table->foreignId('buyer_vendor_id')->nullable()->constrained('vendors')->nullOnDelete()->after('vendor_id');
            $table->index('buyer_vendor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['buyer_vendor_id']);
            $table->dropIndex(['buyer_vendor_id']);
            $table->dropColumn('buyer_vendor_id');
        });
    }
};
