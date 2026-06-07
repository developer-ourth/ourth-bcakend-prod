<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['upi', 'card', 'netbanking', 'wallet', 'cod']);
            $table->string('provider', 100)->nullable();   // Paytm, GPay, PhonePe, Visa…
            $table->string('identifier', 255)->nullable(); // UPI ID or last 4 card digits
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
