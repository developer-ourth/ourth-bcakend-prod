<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recycling_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_number')->unique();
            $table->string('facility_name');
            $table->string('facility_city');
            $table->date('processing_date');
            $table->decimal('input_weight_kg', 10, 2);
            $table->decimal('recycled_weight_kg', 10, 2)->default(0);
            $table->decimal('rejected_weight_kg', 10, 2)->default(0);
            $table->enum('material_type', ['plastic', 'paper', 'glass', 'metal', 'organic', 'e_waste', 'mixed']);
            $table->decimal('recycling_efficiency_percent', 5, 2)->nullable();
            $table->decimal('co2_saved_kg', 10, 2)->nullable();
            $table->string('certificate_url')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('processing_date');
            $table->index('material_type');
            $table->index('facility_city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recycling_records');
    }
};
