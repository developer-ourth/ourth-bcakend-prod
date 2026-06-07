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
        Schema::create('vendor_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->onDelete('cascade');
            $table->enum('approval_stage', [
                'pending_documents',
                'documents_submitted',
                'under_review',
                'address_verification',
                'approved',
                'rejected',
            ])->default('pending_documents');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('address_verified_by')->nullable()->constrained('users');
            $table->timestamp('address_verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('rejection_notes')->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('approval_stage');
            $table->index('vendor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_approvals');
    }
};
