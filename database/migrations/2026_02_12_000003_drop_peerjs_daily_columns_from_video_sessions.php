<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('video_sessions', 'host_peer_id')) {
                $table->dropColumn('host_peer_id');
            }
            if (Schema::hasColumn('video_sessions', 'participant_peer_id')) {
                $table->dropColumn('participant_peer_id');
            }
            if (Schema::hasColumn('video_sessions', 'daily_room_url')) {
                $table->dropColumn('daily_room_url');
            }
            if (Schema::hasColumn('video_sessions', 'daily_room_name')) {
                $table->dropColumn('daily_room_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('video_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('video_sessions', 'host_peer_id')) {
                $table->string('host_peer_id')->nullable();
            }
            if (! Schema::hasColumn('video_sessions', 'participant_peer_id')) {
                $table->string('participant_peer_id')->nullable();
            }
            if (! Schema::hasColumn('video_sessions', 'daily_room_url')) {
                $table->string('daily_room_url')->nullable();
            }
            if (! Schema::hasColumn('video_sessions', 'daily_room_name')) {
                $table->string('daily_room_name')->nullable();
            }
        });
    }
};
