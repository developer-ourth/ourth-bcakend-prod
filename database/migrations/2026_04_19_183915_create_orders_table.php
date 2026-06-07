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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained()->onDelete('restrict');
            $table->enum('order_status', ['pending', 'confirmed', 'processing', 'packed', 'dispatched', 'in_transit', 'out_for_delivery', 'delivered', 'cancelled', 'returned'])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('delivery_charge', 8, 2)->default(0);
            $table->decimal('tax_amount', 8, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->string('delivery_address_line1');
            $table->string('delivery_address_line2')->nullable();
            $table->string('delivery_city');
            $table->string('delivery_state');
            $table->string('delivery_postal_code', 10);
            $table->string('delivery_country')->default('India');
            $table->string('delivery_phone', 15);
            $table->text('customer_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('vendor_id');
            $table->index('order_status');
            $table->index('payment_status');
            $table->index('created_at');
            $table->index(['vendor_id', 'order_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
