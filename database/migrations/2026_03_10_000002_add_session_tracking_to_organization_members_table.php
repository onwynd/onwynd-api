<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_members', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_members', 'sessions_used_this_month')) {
                $table->unsignedSmallInteger('sessions_used_this_month')
                    ->default(0)
                    ->after('department')
                    ->comment('Number of corporate session credits consumed this calendar month');
            }

            if (! Schema::hasColumn('organization_members', 'sessions_limit')) {
                $table->unsignedSmallInteger('sessions_limit')
                    ->default(0)
                    ->after('sessions_used_this_month')
                    ->comment('Monthly session allowance for this member (0 = Starter / no coverage)');
            }

            if (! Schema::hasColumn('organization_members', 'session_duration_minutes')) {
                $table->unsignedSmallInteger('session_duration_minutes')
                    ->nullable()
                    ->after('sessions_limit')
                    ->comment('Session duration in minutes — 35 for Growth, null for Starter');
            }

            if (! Schema::hasColumn('organization_members', 'last_reset_at')) {
                $table->timestamp('last_reset_at')
                    ->nullable()
                    ->after('session_duration_minutes')
                    ->comment('When sessions_used_this_month was last reset to 0 (runs on 1st of each month)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organization_members', function (Blueprint $table) {
            if (Schema::hasColumn('organization_members', 'last_reset_at')) {
                $table->dropColumn('last_reset_at');
            }
            if (Schema::hasColumn('organization_members', 'session_duration_minutes')) {
                $table->dropColumn('session_duration_minutes');
            }
            if (Schema::hasColumn('organization_members', 'sessions_limit')) {
                $table->dropColumn('sessions_limit');
            }
            if (Schema::hasColumn('organization_members', 'sessions_used_this_month')) {
                $table->dropColumn('sessions_used_this_month');
            }
        });
    }
};
