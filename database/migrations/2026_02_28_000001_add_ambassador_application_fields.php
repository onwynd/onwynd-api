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
        Schema::table('ambassadors', function (Blueprint $table) {
            $table->text('reason')->nullable();
            $table->text('experience')->nullable();
            $table->json('social_media')->nullable();
            $table->integer('total_referrals')->default(0);
            $table->integer('active_referrals')->default(0);
            $table->decimal('total_earnings', 10, 2)->default(0);
            $table->integer('current_month_referrals')->default(0);
            $table->integer('rank')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ambassadors', function (Blueprint $table) {
            $table->dropColumn([
                'reason',
                'experience',
                'social_media',
                'total_referrals',
                'active_referrals',
                'total_earnings',
                'current_month_referrals',
                'rank',
            ]);
        });
    }
};
