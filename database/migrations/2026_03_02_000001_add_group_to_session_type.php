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
        // Add 'group' to the session_type enum
        DB::statement("ALTER TABLE therapy_sessions MODIFY COLUMN session_type ENUM('video', 'audio', 'chat', 'group') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'group' from the session_type enum
        DB::statement("ALTER TABLE therapy_sessions MODIFY COLUMN session_type ENUM('video', 'audio', 'chat') NOT NULL");
    }
};
