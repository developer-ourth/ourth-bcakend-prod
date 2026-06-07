<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sustainability_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->unsignedInteger('green_points')->default(0);
            $table->unsignedInteger('carbon_points')->default(0);
            $table->unsignedInteger('total_points')->default(0);
            $table->string('tier')->default('bronze');
            $table->decimal('plastic_avoided_kg', 10, 2)->default(0);
            $table->decimal('co2_saved_kg', 10, 2)->default(0);
            $table->unsignedInteger('eco_orders_count')->default(0);
            $table->unsignedInteger('bins_used_count')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('tier');
            $table->index('total_points');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sustainability_scores');
    }
};
