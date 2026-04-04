<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapist_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('therapist_profiles', 'has_35min_slot')) {
                $table->boolean('has_35min_slot')
                    ->default(false)
                    ->after('hourly_rate')
                    ->comment('Therapist opts in to offer 35-minute corporate sessions');
            }

            if (! Schema::hasColumn('therapist_profiles', 'rate_35min')) {
                $table->unsignedInteger('rate_35min')
                    ->nullable()
                    ->after('has_35min_slot')
                    ->comment('NGN rate for a 35-minute session — set independently, not derived from hourly_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('therapist_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('therapist_profiles', 'rate_35min')) {
                $table->dropColumn('rate_35min');
            }
            if (Schema::hasColumn('therapist_profiles', 'has_35min_slot')) {
                $table->dropColumn('has_35min_slot');
            }
        });
    }
};
