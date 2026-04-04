<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('therapy_session_id')->unique(); // One video session per therapy session
            $table->uuid('host_id'); // Therapist
            $table->uuid('participant_id'); // User

            // Session State
            $table->string('provider')->default('peerjs'); // peerjs, daily
            $table->string('status')->default('scheduled'); // scheduled, active, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->default(0);

            // Connection Info
            $table->string('host_peer_id')->nullable();
            $table->string('participant_peer_id')->nullable();
            $table->string('daily_room_url')->nullable();
            $table->string('daily_room_name')->nullable();

            // Quality Metrics
            $table->json('quality_metrics')->nullable(); // Packet loss, latency, etc.
            $table->string('disconnect_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('video_recordings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('video_session_id');
            $table->string('storage_path');
            $table->string('storage_disk')->default('s3');
            $table->string('filename');
            $table->string('mime_type')->default('video/webm');
            $table->bigInteger('size_bytes')->default(0);
            $table->integer('duration_seconds')->default(0);
            $table->string('status')->default('processing'); // processing, completed, failed
            $table->json('metadata')->nullable(); // Resolution, bitrate, etc.

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('video_session_id')->references('id')->on('video_sessions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_recordings');
        Schema::dropIfExists('video_sessions');
    }
};
