<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quota_settings', function (Blueprint $table) {
            // First-time user quota (days 1–7)
            $table->unsignedInteger('new_user_ai_messages')->default(15)->after('free_ai_messages');
            // How many days a user is considered "new"
            $table->unsignedInteger('new_user_days')->default(7)->after('new_user_ai_messages');
            // Extra messages granted when high-distress signals are detected
            $table->unsignedInteger('distress_extension_messages')->default(5)->after('new_user_days');
            // Hard cap applied when abuse/spam patterns are detected
            $table->unsignedInteger('abuse_cap_messages')->default(5)->after('distress_extension_messages');
        });
    }

    public function down(): void
    {
        Schema::table('quota_settings', function (Blueprint $table) {
            $table->dropColumn([
                'new_user_ai_messages',
                'new_user_days',
                'distress_extension_messages',
                'abuse_cap_messages',
            ]);
        });
    }
};
