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
        Schema::table('notification_settings', function (Blueprint $table) {
            // Channel-specific globals
            $table->boolean('push_notifications')->default(true)->after('sms_notifications');
            $table->boolean('whatsapp_notifications')->default(true)->after('push_notifications');

            // Feature-specific preferences
            $table->boolean('wellbeing_checkins')->default(true)->after('appointment_reminders');
            $table->boolean('platform_updates')->default(true)->after('wellbeing_checkins');

            // Detailed channel mapping (JSON) for granular control
            // Example: { "session_reminders": ["email", "push", "whatsapp"], "wellbeing": ["push"] }
            $table->json('channel_preferences')->nullable()->after('platform_updates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropColumn([
                'push_notifications',
                'whatsapp_notifications',
                'wellbeing_checkins',
                'platform_updates',
                'channel_preferences',
            ]);
        });
    }
};
