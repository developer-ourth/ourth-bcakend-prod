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
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Business Information
            $table->string('business_name');
            $table->string('business_category')->nullable();
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();

            // KYC Information
            $table->string('gstin', 15)->unique()->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('trade_license_number')->nullable();
            $table->date('trade_license_expiry')->nullable();

            // Banking Information
            $table->string('bank_account_number')->nullable();
            $table->string('bank_ifsc_code', 11)->nullable();
            $table->string('bank_account_holder_name')->nullable();

            // KYC Status
            $table->enum('kyc_status', ['pending', 'under_review', 'verified', 'rejected'])->default('pending');
            $table->timestamp('kyc_verified_at')->nullable();
            $table->foreignId('kyc_verified_by')->nullable()->constrained('users');
            $table->text('kyc_rejection_reason')->nullable();

            // Address
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('postal_code', 10);
            $table->string('country')->default('India');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Ratings & Stats
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->unsignedInteger('total_ratings_count')->default(0);
            $table->string('qr_code_id')->unique()->nullable();
            $table->unsignedBigInteger('total_orders')->default(0);
            $table->decimal('total_revenue', 15, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('kyc_status');
            $table->index('business_category');
            $table->index(['city', 'state']);
            $table->index('average_rating');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
