<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            // 'open'   — actively hiring
            // 'filled' — position has been taken; visible for 7 days then auto-hidden
            // 'closed' — manually closed by admin; hidden immediately
            $table->string('status', 20)->default('open')->after('is_active');
            $table->timestamp('filled_at')->nullable()->after('status');
        });

        // Back-fill: map existing is_active flag to new status column
        DB::statement("UPDATE job_postings SET status = CASE WHEN is_active = 1 THEN 'open' ELSE 'closed' END");
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn(['status', 'filled_at']);
        });
    }
};
