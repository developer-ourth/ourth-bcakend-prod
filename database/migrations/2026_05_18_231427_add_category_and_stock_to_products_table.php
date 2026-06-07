<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This migration is a duplicate of _231426_. Guard so fresh installs do not fail.
        if (Schema::hasColumn('products', 'category_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->change();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete()->after('vendor_id');
            $table->unsignedInteger('stock_quantity')->default(0)->after('is_featured');
            $table->string('unit', 30)->default('piece')->after('stock_quantity');
        });
    }

    public function down(): void
    {
        // No-op: the _231426 migration owns the rollback.
    }
};
