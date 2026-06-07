<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_settings', function (Blueprint $table) {
            $table->id();
            $table->string('city')->unique();
            $table->string('state');
            $table->string('country')->default('India');
            $table->enum('status', ['planning', 'pilot', 'active', 'paused', 'discontinued'])->default('planning');
            $table->date('launch_date')->nullable();
            $table->unsignedInteger('target_vendors')->default(0);
            $table->unsignedInteger('target_consumers')->default(0);
            $table->decimal('delivery_radius_km', 8, 2)->default(10);
            $table->decimal('min_order_value', 8, 2)->default(0);
            $table->decimal('delivery_charge', 8, 2)->default(0);
            $table->decimal('free_delivery_above', 10, 2)->nullable();
            $table->json('active_features')->nullable();
            $table->json('restricted_features')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('city_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_settings');
    }
};
