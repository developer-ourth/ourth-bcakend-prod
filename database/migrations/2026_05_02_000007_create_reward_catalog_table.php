<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('reward_type', ['cashback', 'discount_coupon', 'free_product', 'points_multiplier', 'gift_card']);
            $table->unsignedInteger('points_required');
            $table->decimal('cashback_amount', 8, 2)->nullable();
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->unsignedInteger('total_quantity')->nullable();
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamps();

            $table->index('reward_type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_catalog');
    }
};
