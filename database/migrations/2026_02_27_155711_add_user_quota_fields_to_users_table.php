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
            $table->integer('custom_ai_messages')->nullable()->after('is_active')->comment('Custom AI message quota for this user');
            $table->integer('custom_daily_activities')->nullable()->after('custom_ai_messages')->comment('Custom daily activity quota for this user');
            $table->integer('grace_period_days')->default(0)->after('custom_daily_activities')->comment('Additional grace period days for this user');
            $table->boolean('has_unlimited_quota')->default(false)->after('grace_period_days')->comment('Whether this user has unlimited quota');
            $table->timestamp('quota_override_expires_at')->nullable()->after('has_unlimited_quota')->comment('When custom quota settings expire');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['custom_ai_messages', 'custom_daily_activities', 'grace_period_days', 'has_unlimited_quota', 'quota_override_expires_at']);
        });
    }
};
