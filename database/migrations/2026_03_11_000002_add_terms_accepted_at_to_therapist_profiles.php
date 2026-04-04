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
                if (! Schema::hasColumn('therapist_profiles', 'terms_accepted_at')) {
                    $table->timestamp('terms_accepted_at')->nullable()->after('founding_started_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('therapist_profiles')) {
            Schema::table('therapist_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('therapist_profiles', 'terms_accepted_at')) {
                    $table->dropColumn('terms_accepted_at');
                }
            });
        }
    }
};
