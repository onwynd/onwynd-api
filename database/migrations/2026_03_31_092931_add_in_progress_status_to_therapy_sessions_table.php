<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE therapy_sessions MODIFY COLUMN status ENUM('scheduled', 'ongoing', 'completed', 'cancelled', 'no_show', 'in_progress') NOT NULL DEFAULT 'scheduled'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE therapy_sessions MODIFY COLUMN status ENUM('scheduled', 'ongoing', 'completed', 'cancelled', 'no_show') NOT NULL DEFAULT 'scheduled'");
    }
};
