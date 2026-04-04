<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quota_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('corporate_grace_period_days')->default(14)->after('abuse_cap_messages');
        });
    }

    public function down(): void
    {
        Schema::table('quota_settings', function (Blueprint $table) {
            $table->dropColumn('corporate_grace_period_days');
        });
    }
};
