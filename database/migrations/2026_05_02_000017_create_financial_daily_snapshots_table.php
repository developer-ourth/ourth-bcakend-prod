<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->decimal('product_revenue', 12, 2)->default(0);
            $table->decimal('subscription_revenue', 12, 2)->default(0);
            $table->decimal('service_revenue', 12, 2)->default(0);
            $table->decimal('daily_burn_rate', 12, 2)->default(0);
            $table->decimal('cash_balance', 14, 2)->default(0);
            $table->unsignedInteger('runway_days')->default(0);
            $table->decimal('cac', 10, 2)->default(0);
            $table->decimal('ltv', 10, 2)->default(0);
            $table->decimal('avg_revenue_per_vendor', 10, 2)->default(0);
            $table->decimal('avg_revenue_per_order', 10, 2)->default(0);
            $table->unsignedInteger('active_vendors')->default(0);
            $table->unsignedInteger('active_consumers')->default(0);
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('gross_margin_percent', 5, 2)->default(0);
            $table->json('revenue_by_city')->nullable();
            $table->timestamps();

            $table->index('snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_daily_snapshots');
    }
};
