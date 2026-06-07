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
        Schema::create('dispatch_slips', function (Blueprint $table) {
            $table->id();
            $table->string('dispatch_number')->unique();
            $table->foreignId('order_id')->unique()->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'approved', 'packing', 'packed', 'handed_over'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('packed_by')->nullable()->constrained('users');
            $table->timestamp('packed_at')->nullable();
            $table->foreignId('handed_over_by')->nullable()->constrained('users');
            $table->timestamp('handed_over_at')->nullable();
            $table->text('packing_notes')->nullable();
            $table->integer('total_packages')->default(1);
            $table->decimal('total_weight_kg', 8, 2)->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('status');
            $table->index('dispatch_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispatch_slips');
    }
};
