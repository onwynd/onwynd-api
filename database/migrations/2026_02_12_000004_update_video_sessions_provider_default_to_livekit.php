<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill existing provider values to 'livekit'
        DB::table('video_sessions')->whereIn('provider', ['peerjs', 'daily'])->update(['provider' => 'livekit']);

        // Change default to 'livekit' if supported by the driver
        Schema::table('video_sessions', function (Blueprint $table) {
            $table->string('provider')->default('livekit')->change();
        });
    }

    public function down(): void
    {
        // Revert default to 'peerjs'
        Schema::table('video_sessions', function (Blueprint $table) {
            $table->string('provider')->default('peerjs')->change();
        });
    }
};
