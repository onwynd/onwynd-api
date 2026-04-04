<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('therapy_sessions', 'anonymous_fingerprint')) {
                $table->string('anonymous_fingerprint', 64)->nullable()->after('is_anonymous');
            }
        });
    }

    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('therapy_sessions', 'anonymous_fingerprint')) {
                $table->dropColumn('anonymous_fingerprint');
            }
        });
    }
};
