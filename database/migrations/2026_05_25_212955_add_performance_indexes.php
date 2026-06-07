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
        // Performance indexes are only meaningful on production-grade drivers.
        // SQLite (used in tests) does not support pg_indexes and doesn't need them.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        // orders — scoped by user, filtered by status, sorted by date
        Schema::table('orders', function (Blueprint $table) {
            if (! $this->hasIndex('orders', 'orders_user_id_index')) {
                $table->index('user_id');
            }
            if (! $this->hasIndex('orders', 'orders_order_status_index')) {
                $table->index('order_status');
            }
            if (! $this->hasIndex('orders', 'orders_created_at_index')) {
                $table->index('created_at');
            }
        });

        // vendors — looked up by vendor_code on every vendor login
        Schema::table('vendors', function (Blueprint $table) {
            if (! $this->hasIndex('vendors', 'vendors_vendor_code_index')) {
                $table->index('vendor_code');
            }
        });

        // carts — joined to cart_items constantly
        Schema::table('carts', function (Blueprint $table) {
            if (! $this->hasIndex('carts', 'carts_user_id_index')) {
                $table->index('user_id');
            }
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['order_status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropIndex(['vendor_code']);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(DB::select(
            'SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?',
            [$table, $index]
        ))->isNotEmpty();
    }
};
