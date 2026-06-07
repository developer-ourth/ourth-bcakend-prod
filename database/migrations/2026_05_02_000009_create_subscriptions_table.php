<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_name');
            $table->enum('frequency', ['daily', 'weekly', 'monthly']);
            $table->enum('status', ['active', 'paused', 'cancelled', 'expired'])->default('active');
            $table->decimal('plan_price', 10, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_delivery_date')->nullable();
            $table->json('delivery_address')->nullable();
            $table->unsignedInteger('total_deliveries')->default(0);
            $table->unsignedInteger('deliveries_completed')->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('vendor_id');
            $table->index('status');
            $table->index('next_delivery_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
