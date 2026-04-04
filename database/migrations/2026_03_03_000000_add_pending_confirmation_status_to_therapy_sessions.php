<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, we need to modify the enum to include the new status
        DB::statement("ALTER TABLE therapy_sessions MODIFY COLUMN status ENUM('scheduled', 'ongoing', 'completed', 'cancelled', 'no_show', 'pending_confirmation') NOT NULL DEFAULT 'scheduled'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the new status and revert to original enum values
        DB::statement("UPDATE therapy_sessions SET status = 'scheduled' WHERE status = 'pending_confirmation'");
        DB::statement("ALTER TABLE therapy_sessions MODIFY COLUMN status ENUM('scheduled', 'ongoing', 'completed', 'cancelled', 'no_show') NOT NULL DEFAULT 'scheduled'");
    }
};
