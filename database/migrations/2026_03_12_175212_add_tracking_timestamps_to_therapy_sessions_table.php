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
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->timestamp('session_started_at')->nullable()->after('scheduled_at');
            $table->timestamp('patient_joined_at')->nullable()->after('session_started_at');
            $table->timestamp('therapist_joined_at')->nullable()->after('patient_joined_at');
            $table->integer('actual_duration_minutes')->nullable()->after('duration_minutes');
            $table->dateTime('completed_at')->nullable()->after('ended_at');

            // Add some missing statuses for completion guards if they aren't in the enum
            // Note: Since status is an enum in the original migration, we might need a separate migration
            // or modify the enum if the database driver supports it. For now, let's just add the columns.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'session_started_at',
                'patient_joined_at',
                'therapist_joined_at',
                'actual_duration_minutes',
                'completed_at',
            ]);
        });
    }
};
