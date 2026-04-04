<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapist_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('therapist_profiles', 'recipient_code')) {
                $table->string('recipient_code')->nullable()->after('is_accepting_clients');
            }
            if (! Schema::hasColumn('therapist_profiles', 'bank_code')) {
                $table->string('bank_code', 10)->nullable()->after('recipient_code');
            }
            if (! Schema::hasColumn('therapist_profiles', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('bank_code');
            }
            if (! Schema::hasColumn('therapist_profiles', 'account_number')) {
                $table->string('account_number', 20)->nullable()->after('bank_name');
            }
            if (! Schema::hasColumn('therapist_profiles', 'account_name')) {
                $table->string('account_name')->nullable()->after('account_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('therapist_profiles', function (Blueprint $table) {
            $table->dropColumn(['recipient_code', 'bank_code', 'bank_name', 'account_number', 'account_name']);
        });
    }
};
