<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('therapist_profiles')) {
            return;
        }

        Schema::table('therapist_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('therapist_profiles', 'introductory_rate')) {
                $table->decimal('introductory_rate', 10, 2)->nullable()->after('hourly_rate')
                    ->comment('Discounted rate for first N sessions with a new patient');
            }
            if (! Schema::hasColumn('therapist_profiles', 'introductory_sessions_count')) {
                $table->unsignedSmallInteger('introductory_sessions_count')->nullable()->after('introductory_rate')
                    ->comment('Number of sessions at introductory rate');
            }
            if (! Schema::hasColumn('therapist_profiles', 'introductory_rate_active')) {
                $table->boolean('introductory_rate_active')->default(false)->after('introductory_sessions_count');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('therapist_profiles')) {
            return;
        }

        Schema::table('therapist_profiles', function (Blueprint $table) {
            $cols = ['introductory_rate', 'introductory_sessions_count', 'introductory_rate_active'];
            $existing = array_filter($cols, fn ($c) => Schema::hasColumn('therapist_profiles', $c));
            if ($existing) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};
