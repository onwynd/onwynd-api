<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Additive AI chat bonus earned through referrals (stacks on top of plan limit)
            $table->unsignedInteger('referral_ai_bonus')->default(0)->after('referred_by_ambassador_code');
            // Cumulative % discount on next renewal earned by paid referrers
            $table->decimal('pending_referral_discount', 5, 2)->default(0)->after('referral_ai_bonus');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['referral_ai_bonus', 'pending_referral_discount']);
        });
    }
};
