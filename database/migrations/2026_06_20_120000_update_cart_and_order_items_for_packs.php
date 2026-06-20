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
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'product_id']);
            $table->foreignId('product_pack_id')->nullable()->after('product_id')->constrained('product_packs')->onDelete('cascade');
            $table->unique(['cart_id', 'product_id', 'product_pack_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_pack_id')->nullable()->after('product_id')->constrained('product_packs')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_pack_id']);
            $table->dropColumn('product_pack_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'product_id', 'product_pack_id']);
            $table->dropForeign(['product_pack_id']);
            $table->dropColumn('product_pack_id');
            $table->unique(['cart_id', 'product_id']);
        });
    }
};
