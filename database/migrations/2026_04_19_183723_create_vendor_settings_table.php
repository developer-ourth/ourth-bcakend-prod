<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendor_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->unique()->constrained()->onDelete('cascade');
            $table->boolean('accept_orders')->default(true);
            $table->boolean('auto_accept_orders')->default(false);
            $table->decimal('minimum_order_value', 10, 2)->default(0);
            $table->decimal('delivery_charge', 8, 2)->default(0);
            $table->boolean('free_delivery_enabled')->default(false);
            $table->decimal('free_delivery_above', 10, 2)->nullable();
            $table->string('notification_email')->nullable();
            $table->string('notification_phone')->nullable();
            $table->boolean('sms_notifications')->default(true);
            $table->boolean('email_notifications')->default(true);
            $table->boolean('push_notifications')->default(true);
            $table->json('operating_hours')->nullable();
            $table->boolean('holiday_mode')->default(false);
            $table->timestamps();

            $table->index('vendor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_settings');
    }
};
