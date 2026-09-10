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
            $table->string('agent_code')->nullable()->after('coupon_id');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->string('agent_code')->nullable()->after('coupon_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('agent_code');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('agent_code');
        });
    }
};
