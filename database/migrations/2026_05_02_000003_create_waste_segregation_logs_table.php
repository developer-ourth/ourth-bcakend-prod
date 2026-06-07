<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_segregation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waste_collection_id')->constrained()->onDelete('cascade');
            $table->decimal('dry_waste_kg', 8, 2)->default(0);
            $table->decimal('wet_waste_kg', 8, 2)->default(0);
            $table->decimal('plastic_waste_kg', 8, 2)->default(0);
            $table->decimal('e_waste_kg', 8, 2)->default(0);
            $table->decimal('hazardous_waste_kg', 8, 2)->default(0);
            $table->decimal('other_waste_kg', 8, 2)->default(0);
            $table->decimal('total_weight_kg', 8, 2)->storedAs('dry_waste_kg + wet_waste_kg + plastic_waste_kg + e_waste_kg + hazardous_waste_kg + other_waste_kg');
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('waste_collection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_segregation_logs');
    }
};
