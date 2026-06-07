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
        Schema::table('carts', function (Blueprint $table) {
            // Drop the existing NOT NULL foreign key constraint
            $table->dropForeign(['vendor_id']);

            // Re-add the column as nullable, keeping the FK
            $table->foreignId('vendor_id')->nullable()->change();
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->foreignId('vendor_id')->nullable(false)->change();
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
        });
    }
};
