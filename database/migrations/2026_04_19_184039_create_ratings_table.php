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
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->string('ratable_type');
            $table->unsignedBigInteger('ratable_id');
            $table->foreignId('reviewer_id')->constrained('users');
            $table->unsignedTinyInteger('rating');
            $table->text('review')->nullable();
            $table->json('review_photos')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->index(['ratable_type', 'ratable_id']);
            $table->index('reviewer_id');
            $table->index('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
