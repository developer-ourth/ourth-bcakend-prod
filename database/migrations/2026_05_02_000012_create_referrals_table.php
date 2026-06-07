<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('referred_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('referral_code')->unique();
            $table->enum('referred_as', ['vendor', 'consumer'])->default('consumer');
            $table->enum('status', ['pending', 'signed_up', 'activated', 'rewarded', 'expired'])->default('pending');
            $table->unsignedInteger('referrer_points_earned')->default(0);
            $table->unsignedInteger('referred_points_earned')->default(0);
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('signed_up_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('referrer_id');
            $table->index('referred_id');
            $table->index('referral_code');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
