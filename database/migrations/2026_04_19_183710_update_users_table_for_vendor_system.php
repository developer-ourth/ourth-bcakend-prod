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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 15)->unique()->nullable()->after('email');
            $table->enum('user_type', ['vendor', 'delivery_partner', 'warehouse_staff', 'admin'])->default('vendor')->after('password');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('user_type');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->string('last_ip_address', 45)->nullable()->after('last_login_at');
            $table->softDeletes();

            $table->index('user_type');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['user_type']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
            $table->dropSoftDeletes();
            $table->dropColumn(['phone', 'user_type', 'status', 'last_login_at', 'last_ip_address']);
        });
    }
};
