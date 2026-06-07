<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('reward_catalog_id')->nullable()->constrained('reward_catalog')->nullOnDelete();
            $table->enum('transaction_type', ['earn', 'redeem', 'expire', 'adjust']);
            $table->unsignedInteger('points');
            $table->unsignedInteger('points_balance_after');
            $table->string('source')->nullable();
            $table->string('source_reference')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('transaction_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_transactions');
    }
};
