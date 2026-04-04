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
            if (! Schema::hasColumn('therapist_profiles', 'account_flagged')) {
                $table->boolean('account_flagged')->default(false);
            }
            if (! Schema::hasColumn('therapist_profiles', 'flag_reason')) {
                $table->string('flag_reason')->nullable();
            }
            if (! Schema::hasColumn('therapist_profiles', 'flag_note')) {
                $table->text('flag_note')->nullable();
            }
            if (! Schema::hasColumn('therapist_profiles', 'flagged_at')) {
                $table->timestamp('flagged_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('therapist_profiles')) {
            return;
        }

        Schema::table('therapist_profiles', function (Blueprint $table) {
            $cols = ['account_flagged', 'flag_reason', 'flag_note', 'flagged_at'];
            $existing = array_filter($cols, fn ($c) => Schema::hasColumn('therapist_profiles', $c));
            if ($existing) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};
