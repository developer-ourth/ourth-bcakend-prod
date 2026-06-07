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
        Schema::table('products', function (Blueprint $table) {
            // Wholesale (B2B) price — null means B2B buyers pay the same retail price
            $table->decimal('wholesale_price', 10, 2)->nullable()->after('cost_price');
            // Minimum order quantity enforced on B2B orders
            $table->unsignedSmallInteger('min_order_quantity')->default(1)->after('wholesale_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['wholesale_price', 'min_order_quantity']);
        });
    }
};
