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
        Schema::create('audio_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('therapy_session_id')->constrained('therapy_sessions')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('audio_level'); // 0-100 decibels
            $table->integer('network_latency_ms'); // milliseconds
            $table->decimal('packet_loss_percent', 5, 2); // 0-100%
            $table->dateTime('recorded_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audio_metrics');
    }
};
