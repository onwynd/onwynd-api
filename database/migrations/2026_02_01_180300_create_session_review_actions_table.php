<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_review_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_review_id')->constrained('session_reviews')->onDelete('cascade');
            $table->foreignUuid('clinical_advisor_id')->constrained('clinical_advisors')->onDelete('cascade');

            // Action Details
            $table->enum('action_type', [
                'approved',
                'flagged',
                'escalated_to_crisis',
                'assigned_monitoring',
                'requested_therapist_improvement',
                'scheduled_follow_up',
                'closed_without_action',
            ]);

            $table->text('action_description');
            $table->text('clinical_notes')->nullable();
            $table->enum('priority', ['low', 'normal', 'urgent', 'critical'])->default('normal');

            // Follow-up
            $table->timestamp('action_completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->foreignUuid('completed_by')->nullable()->constrained('clinical_advisors');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_review_actions');
    }
};
