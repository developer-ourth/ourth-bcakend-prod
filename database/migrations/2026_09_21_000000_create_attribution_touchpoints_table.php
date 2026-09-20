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
        Schema::create('attribution_touchpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type', 50); // 'meta_lead_ad', 'whatsapp_ctwa', 'organic_web'
            $table->string('ad_id', 100)->nullable()->index();
            $table->string('adset_id', 100)->nullable();
            $table->string('campaign_id', 100)->nullable();
            $table->string('campaign_name', 255)->nullable();
            $table->string('form_id', 100)->nullable();
            $table->text('fbclid')->nullable();
            $table->text('fbp')->nullable();
            $table->text('fbc')->nullable();
            $table->string('client_ip_address', 45)->nullable();
            $table->text('client_user_agent')->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('order_touchpoint_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->uuid('touchpoint_id')->constrained('attribution_touchpoints')->cascadeOnDelete();
            $table->string('touchpoint_role', 30)->default('last_touch'); // 'first_touch', 'last_touch', 'assisting'
            $table->decimal('weight', 5, 4)->default(1.0000);
            $table->timestamps();
        });

        Schema::create('meta_capi_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->string('event_name', 50); // 'Purchase', 'Lead', 'AddToCart'
            $table->string('event_id', 100);
            $table->string('status', 20); // 'SUCCESS', 'FAILED'
            $table->jsonb('response_body')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_capi_logs');
        Schema::dropIfExists('order_touchpoint_mappings');
        Schema::dropIfExists('attribution_touchpoints');
    }
};
