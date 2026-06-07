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
        Schema::create('delivery_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('rider_lat', 10, 7)->nullable();
            $table->decimal('rider_lng', 10, 8)->nullable();
            $table->decimal('bearing', 5, 2)->default(0)->comment('Direction rider is heading, 0-360 degrees');
            $table->string('status_message')->nullable()->comment('Human-readable ETA or status note');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_trackings');
    }
};
