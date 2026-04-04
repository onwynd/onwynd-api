<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE therapy_sessions MODIFY COLUMN status ENUM('scheduled', 'ongoing', 'completed', 'cancelled', 'no_show', 'pending_confirmation', 'ended_early') NOT NULL DEFAULT 'scheduled'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("UPDATE therapy_sessions SET status = 'completed' WHERE status = 'ended_early'");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE therapy_sessions MODIFY COLUMN status ENUM('scheduled', 'ongoing', 'completed', 'cancelled', 'no_show', 'pending_confirmation') NOT NULL DEFAULT 'scheduled'");
    }
};
