<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('therapist_profiles')) {
            Schema::table('therapist_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('therapist_profiles', 'is_founding')) {
                    $table->boolean('is_founding')->default(false)->after('is_verified');
                }
                if (! Schema::hasColumn('therapist_profiles', 'founding_started_at')) {
                    $table->timestamp('founding_started_at')->nullable()->after('is_founding');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('therapist_profiles')) {
            Schema::table('therapist_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('therapist_profiles', 'founding_started_at')) {
                    $table->dropColumn('founding_started_at');
                }
                if (Schema::hasColumn('therapist_profiles', 'is_founding')) {
                    $table->dropColumn('is_founding');
                }
            });
        }
    }
};
