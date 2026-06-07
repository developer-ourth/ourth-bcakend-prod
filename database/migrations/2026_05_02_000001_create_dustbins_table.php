<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dustbins', function (Blueprint $table) {
            $table->id();
            $table->string('qr_code')->unique();
            $table->string('bin_label')->nullable();
            $table->string('city');
            $table->string('area')->nullable();
            $table->string('location_description')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->enum('bin_type', ['general', 'dry', 'wet', 'plastic', 'e_waste', 'mixed'])->default('mixed');
            $table->unsignedSmallInteger('capacity_litres')->default(100);
            $table->unsignedTinyInteger('fill_level_percent')->default(0);
            $table->enum('status', ['active', 'inactive', 'full', 'maintenance'])->default('active');
            $table->foreignId('assigned_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->timestamp('last_emptied_at')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->unsignedInteger('total_scans')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('city');
            $table->index('status');
            $table->index('bin_type');
            $table->index('fill_level_percent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dustbins');
    }
};
