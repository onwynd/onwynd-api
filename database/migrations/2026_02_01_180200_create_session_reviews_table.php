<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('therapy_session_id')->constrained('therapy_sessions');
            $table->foreignId('therapist_id')->constrained('users');
            $table->foreignId('user_id')->constrained('users');

            // Risk Assessment
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical']);
            $table->json('risk_flags'); // ["suicidal_ideation", "abuse_indicator"]
            $table->text('risk_summary');
            $table->string('recommended_action'); // "escalate_to_crisis", "monitor", etc
            $table->integer('risk_confidence_score'); // 0-100

            // Clinical Quality
            $table->integer('empathy_score'); // 0-100
            $table->integer('clinical_accuracy_score'); // 0-100
            $table->integer('directiveness_score'); // 0-100
            $table->integer('pacing_score'); // 0-100
            $table->integer('overall_session_quality_score'); // 0-100
            $table->json('strengths'); // ["Good empathy", "Clear goals"]
            $table->json('opportunities'); // ["Explore more", "Better pacing"]
            $table->json('peer_comparison')->nullable(); // {"percentile": 82, "rank": "top 20%"}

            // Outcome Prediction
            $table->integer('predicted_improvement_percentage')->nullable(); // 0-100
            $table->integer('outcome_confidence_score')->nullable(); // 0-100
            $table->json('success_factors')->nullable();
            $table->json('risk_factors')->nullable();

            // Treatment Alignment
            $table->integer('treatment_alignment_score')->nullable(); // 0-100
            $table->boolean('addressed_treatment_goals')->default(false);
            $table->boolean('homework_completed')->default(false);
            $table->json('recommendations')->nullable();

            // Compliance
            $table->integer('compliance_score')->nullable(); // 0-100
            $table->json('compliance_flags')->nullable(); // ["missing_documentation", "boundary_concern"]
            $table->text('compliance_notes')->nullable();

            // Meta
            $table->enum('review_status', ['pending', 'approved', 'flagged', 'escalated'])->default('pending');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('clinical_advisors'); // Clinical Advisor who reviewed it
            $table->timestamp('reviewed_at')->nullable();
            $table->text('clinical_advisor_notes')->nullable();

            // Processing
            $table->string('ai_model_used'); // "gpt-4", "claude-3", etc
            $table->integer('processing_time_seconds');
            $table->json('full_ai_response')->nullable(); // Store full response for audit

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_reviews');
    }
};
