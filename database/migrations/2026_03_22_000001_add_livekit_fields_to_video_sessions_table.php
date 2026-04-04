<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('video_sessions', 'room_name')) {
                $table->string('room_name')->nullable()->after('provider');
            }
            if (! Schema::hasColumn('video_sessions', 'therapist_token')) {
                $table->text('therapist_token')->nullable()->after('room_name');
            }
            if (! Schema::hasColumn('video_sessions', 'patient_token')) {
                $table->text('patient_token')->nullable()->after('therapist_token');
            }
            if (! Schema::hasColumn('video_sessions', 'prepared_at')) {
                $table->timestamp('prepared_at')->nullable()->after('patient_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('video_sessions', function (Blueprint $table) {
            $columns = array_filter(
                ['room_name', 'therapist_token', 'patient_token', 'prepared_at'],
                fn (string $col) => Schema::hasColumn('video_sessions', $col)
            );
            if ($columns) {
                $table->dropColumn(array_values($columns));
            }
        });
    }
};
