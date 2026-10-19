<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CRM fields kept separate from users.user_type, which drives the vendor
     * system and must not be overloaded with sales segments.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'crm_segment')) {
                $table->string('crm_segment', 30)->nullable()->index(); // 'B2C', 'B2B_Distributor', 'Vendor'
            }
            if (! Schema::hasColumn('users', 'krayin_person_id')) {
                $table->unsignedBigInteger('krayin_person_id')->nullable();
            }
            if (! Schema::hasColumn('users', 'krayin_lead_id')) {
                $table->unsignedBigInteger('krayin_lead_id')->nullable()->index();
            }
        });

        // Columns used by MetaCapiService and the attribution dashboard but never migrated.
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'capi_synced')) {
                $table->boolean('capi_synced')->default(false);
            }
            if (! Schema::hasColumn('orders', 'capi_event_id')) {
                $table->string('capi_event_id', 100)->nullable();
            }
            if (! Schema::hasColumn('orders', 'attributed_touchpoint_id')) {
                $table->uuid('attributed_touchpoint_id')->nullable()->index();
                $table->foreign('attributed_touchpoint_id')
                    ->references('id')->on('attribution_touchpoints')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'attributed_touchpoint_id')) {
                $table->dropForeign(['attributed_touchpoint_id']);
                $table->dropColumn('attributed_touchpoint_id');
            }
            $table->dropColumn(array_values(array_filter(
                ['capi_synced', 'capi_event_id'],
                fn ($column) => Schema::hasColumn('orders', $column)
            )));
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'crm_segment')) {
                $table->dropIndex(['crm_segment']);
            }
            if (Schema::hasColumn('users', 'krayin_lead_id')) {
                $table->dropIndex(['krayin_lead_id']);
            }
            $table->dropColumn(array_values(array_filter(
                ['crm_segment', 'krayin_person_id', 'krayin_lead_id'],
                fn ($column) => Schema::hasColumn('users', $column)
            )));
        });
    }
};
