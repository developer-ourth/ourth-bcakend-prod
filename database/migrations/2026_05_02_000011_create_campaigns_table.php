<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['digital', 'offline', 'referral', 'push', 'sms', 'email', 'social_media']);
            $table->enum('target_audience', ['vendors', 'consumers', 'both'])->default('both');
            $table->enum('status', ['draft', 'scheduled', 'active', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->decimal('budget', 12, 2)->nullable();
            $table->decimal('amount_spent', 12, 2)->default(0);
            $table->string('city')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('conversions')->default(0);
            $table->unsignedInteger('signups_from_campaign')->default(0);
            $table->string('promo_code')->unique()->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('type');
            $table->index('status');
            $table->index('city');
            $table->index('start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
