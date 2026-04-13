<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A later migration (2026_03_31_092931_add_in_progress_status_to_therapy_sessions_table)
     * replaced the status ENUM and accidentally dropped values such as pending_confirmation
     * and ended_early, causing MySQL 1265 "Data truncated for column 'status'" on booking.
     * Using VARCHAR avoids ENUM drift as new workflow states are added.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE therapy_sessions MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'scheduled'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-reversible: restoring a finite ENUM can break rows that use newer status strings.
    }
};
