<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('mental_health_goals')->nullable()->after('last_seen_at');
            $table->json('preferences')->nullable()->after('mental_health_goals');
            $table->timestamp('onboarding_completed_at')->nullable()->after('preferences');
            $table->timestamp('privacy_consent_given_at')->nullable()->after('onboarding_completed_at');
            $table->unsignedInteger('onboarding_step')->default(0)->after('privacy_consent_given_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mental_health_goals', 'preferences', 'onboarding_completed_at', 'privacy_consent_given_at', 'onboarding_step']);
        });
    }
};
