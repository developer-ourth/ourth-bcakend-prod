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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable();
            $table->string('category');
            $table->string('sub_category')->nullable();
            $table->decimal('base_price', 10, 2);
            $table->decimal('discounted_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->string('primary_image_url')->nullable();
            $table->json('secondary_images')->nullable();
            $table->decimal('weight_grams', 8, 2)->nullable();
            $table->json('dimensions_cm')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('vendor_id');
            $table->index('category');
            $table->index('is_active');
            $table->index('sku');
        });

        // fullText is not supported by SQLite (used in tests) — only add on capable drivers.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('products', function (Blueprint $table) {
                $table->fullText(['name', 'description']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
