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
        Schema::create('vendor_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->onDelete('cascade');
            $table->string('qr_code_id')->unique();
            $table->string('qr_code_image_url');
            $table->string('qr_code_data');
            $table->enum('status', ['active', 'expired', 'replaced'])->default('active');
            $table->unsignedBigInteger('scans_count')->default(0);
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('replaced_by')->nullable()->constrained('vendor_qr_codes');
            $table->timestamp('replaced_at')->nullable();
            $table->timestamps();

            $table->index('vendor_id');
            $table->index('status');
            $table->index('qr_code_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_qr_codes');
    }
};
