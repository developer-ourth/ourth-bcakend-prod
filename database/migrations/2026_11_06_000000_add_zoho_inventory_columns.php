<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links between website records and Zoho Inventory records. Zoho ids are
     * large numeric strings, so they are stored as strings.
     */
    public function up(): void
    {
        foreach (['products', 'product_packs'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('zoho_item_id', 40)->nullable()->index();
                $table->timestamp('zoho_stock_synced_at')->nullable();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('zoho_contact_id', 40)->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('zoho_salesorder_id', 40)->nullable()->index();
            $table->string('zoho_invoice_id', 40)->nullable();
            $table->string('zoho_payment_id', 40)->nullable();
            $table->timestamp('zoho_synced_at')->nullable();
            $table->text('zoho_sync_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['zoho_salesorder_id']);
            $table->dropColumn(['zoho_salesorder_id', 'zoho_invoice_id', 'zoho_payment_id', 'zoho_synced_at', 'zoho_sync_error']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('zoho_contact_id');
        });

        foreach (['products', 'product_packs'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['zoho_item_id']);
                $table->dropColumn(['zoho_item_id', 'zoho_stock_synced_at']);
            });
        }
    }
};
