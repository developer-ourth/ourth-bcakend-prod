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
        Schema::create('vendor_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->onDelete('cascade');
            $table->date('stats_date');
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('total_revenue', 10, 2)->default(0);
            $table->unsignedInteger('delivered_orders')->default(0);
            $table->unsignedInteger('cancelled_orders')->default(0);
            $table->unsignedInteger('returned_orders')->default(0);
            $table->decimal('average_order_value', 10, 2)->default(0);
            $table->unsignedInteger('unique_customers')->default(0);
            $table->timestamps();

            $table->unique(['vendor_id', 'stats_date']);
            $table->index('stats_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_daily_stats');
    }
};
