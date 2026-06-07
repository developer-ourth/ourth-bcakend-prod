<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impact_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date')->unique();
            $table->string('city')->nullable();
            $table->decimal('plastic_avoided_kg', 12, 2)->default(0);
            $table->decimal('landfill_reduction_kg', 12, 2)->default(0);
            $table->decimal('co2_saved_kg', 12, 2)->default(0);
            $table->decimal('trees_saved_equivalent', 10, 2)->default(0);
            $table->unsignedInteger('dustbins_active')->default(0);
            $table->unsignedInteger('collections_completed')->default(0);
            $table->decimal('total_waste_collected_kg', 12, 2)->default(0);
            $table->decimal('recycling_rate_percent', 5, 2)->default(0);
            $table->unsignedInteger('vendors_using_eco_products')->default(0);
            $table->unsignedInteger('eco_orders_count')->default(0);
            $table->timestamps();

            $table->index('metric_date');
            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impact_metrics');
    }
};
