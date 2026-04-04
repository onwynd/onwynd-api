<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores AI audit results for therapy sessions.
 *
 * Flow:
 *   LiveKit room starts (session-{uuid})
 *     → audit_agent.py joins as silent participant
 *     → streams audio to STT at STT_URL
 *     → POSTs transcript segments to /api/v1/internal/session-audit/segment
 *     → on room close: full transcript + LLM violation analysis
 *         → POST /api/v1/internal/session-audit/complete
 *     → stored here, surfaced in /admin/sessions/{uuid}/review
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('session_uuid')->index();   // matches TherapySession.uuid
            $table->string('room_name')->index();       // e.g. session-{uuid}

            // Transcript
            $table->longText('transcript')->nullable();  // full plain-text transcript
            $table->json('segments')->nullable();         // [{speaker, text, start_ms, end_ms}, ...]

            // Violation analysis
            $table->string('audit_status')->default('pending');
            // pending | processing | clean | flagged | error
            $table->decimal('risk_score', 4, 2)->nullable(); // 0.00–1.00
            $table->json('violations')->nullable();
            // [{type, severity, quote, timestamp_ms, recommendation}, ...]

            // Review
            $table->boolean('reviewed')->default(false);
            $table->unsignedBigInteger('reviewed_by')->nullable()->constrained('users');
            $table->text('reviewer_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            // Agent metadata
            $table->string('agent_version')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('error_message')->nullable();

            $table->timestamps();

            $table->unique('session_uuid'); // one audit log per session
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_audit_logs');
    }
};
