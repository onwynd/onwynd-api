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
        // Change severity_level from enum to a plain string/text column so
        // arbitrary labels can be stored without triggering SQL errors.
        Schema::table('user_assessment_results', function (Blueprint $table) {
            // On MySQL this will convert the enum to varchar(255) automatically;
            // doctrine/dbal must be installed for the change() modifier.
            $table->string('severity_level')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_assessment_results', function (Blueprint $table) {
            $table->enum('severity_level', ['minimal', 'mild', 'moderate', 'severe', 'very_severe'])
                ->nullable()
                ->change();
        });
    }
};
