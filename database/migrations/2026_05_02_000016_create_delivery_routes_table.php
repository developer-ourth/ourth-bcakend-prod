<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_routes', function (Blueprint $table) {
            $table->id();
            $table->string('route_number')->unique();
            $table->date('route_date');
            $table->string('city');
            $table->foreignId('delivery_partner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned');
            $table->unsignedInteger('total_stops')->default(0);
            $table->unsignedInteger('completed_stops')->default(0);
            $table->decimal('total_distance_km', 8, 2)->nullable();
            $table->decimal('estimated_duration_minutes', 8, 2)->nullable();
            $table->decimal('actual_duration_minutes', 8, 2)->nullable();
            $table->json('waypoints')->nullable();
            $table->json('optimized_order')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('route_date');
            $table->index('city');
            $table->index('delivery_partner_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_routes');
    }
};
