<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mindfulness_activities', function (Blueprint $table) {
            $table->string('audio_file_path')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mindfulness_activities', function (Blueprint $table) {
            $table->dropColumn('audio_file_path');
        });
    }
};
