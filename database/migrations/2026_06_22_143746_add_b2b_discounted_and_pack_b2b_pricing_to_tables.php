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
            if (!Schema::hasColumn('products', 'wholesale_discounted_price')) {
                $table->decimal('wholesale_discounted_price', 10, 2)->nullable()->after('wholesale_price');
            }
        });

        Schema::table('product_packs', function (Blueprint $table) {
            if (!Schema::hasColumn('product_packs', 'wholesale_price')) {
                $table->decimal('wholesale_price', 10, 2)->nullable()->after('discounted_price');
            }
            if (!Schema::hasColumn('product_packs', 'wholesale_discounted_price')) {
                $table->decimal('wholesale_discounted_price', 10, 2)->nullable()->after('wholesale_price');
            }
            if (!Schema::hasColumn('product_packs', 'min_order_quantity')) {
                $table->unsignedSmallInteger('min_order_quantity')->default(1)->after('wholesale_discounted_price');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'wholesale_discounted_price')) {
                $table->dropColumn('wholesale_discounted_price');
            }
        });

        Schema::table('product_packs', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('product_packs', 'wholesale_price')) {
                $cols[] = 'wholesale_price';
            }
            if (Schema::hasColumn('product_packs', 'wholesale_discounted_price')) {
                $cols[] = 'wholesale_discounted_price';
            }
            if (Schema::hasColumn('product_packs', 'min_order_quantity')) {
                $cols[] = 'min_order_quantity';
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
