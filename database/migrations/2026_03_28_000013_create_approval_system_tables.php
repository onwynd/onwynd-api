<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic approval engine.
 *
 * Flow diagram for a 3-step approval:
 *
 *   SUBMITTER
 *      │
 *      ▼ submit()
 *   approval_requests [status=pending, current_step=1]
 *      │
 *      ├──▶ Step 1 [pending] ──▶ approve() ──▶ Step 2 [pending]
 *      │                        review()  ──▶ Step 1 [under_review] ──▶ submitter responds ──▶ Step 1 [pending]
 *      │                        reject()  ──▶ request [rejected] ──▶ notify submitter
 *      │
 *      └──▶ Step 2 [pending] ──▶ approve() ──▶ Step 3 [pending]
 *                │
 *                └──▶ Step 3 [pending] ──▶ approve() ──▶ request [approved] ──▶ notify submitter
 *
 * Escalation: if step.due_at passes with no action → notify next level approver
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Main approval request ─────────────────────────────────────────────
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('type');                              // leave|budget|promotion|transfer|expense|termination|custom
            $table->string('title');
            $table->text('description')->nullable();

            // Polymorphic subject — the entity being approved
            $table->string('subject_type')->nullable();          // e.g. App\Models\LeaveRequest
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id']);

            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('current_step')->default(1);
            $table->tinyInteger('total_steps')->default(1);

            $table->enum('status', [
                'draft',
                'pending',           // In the approval chain
                'under_review',      // Sent back to submitter for more info
                'approved',
                'rejected',
                'cancelled',
                'escalated',
            ])->default('pending');

            $table->json('metadata')->nullable();                // Extra context (e.g. amount, days, reason)
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Individual steps in a workflow ────────────────────────────────────
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')
                  ->constrained()->cascadeOnDelete();
            $table->tinyInteger('step_number');
            $table->string('step_label');                        // e.g. "Direct Manager Review"

            // Who should approve this step — resolved to a specific user at creation time
            $table->string('approver_role')->nullable();         // Role slug or approver type (direct_manager, dept_head, hr...)
            $table->foreignId('approver_id')->nullable()         // Resolved specific approver
                  ->constrained('users')->nullOnDelete();

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'under_review',      // Step sent back to submitter
                'skipped',
                'escalated',
            ])->default('pending');

            // Action recorded
            $table->foreignId('actioned_by')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->text('action_notes')->nullable();            // Approval notes / review questions
            $table->text('submitter_response')->nullable();      // Response when under_review
            $table->timestamp('actioned_at')->nullable();

            // Escalation window
            $table->timestamp('due_at')->nullable();
            $table->boolean('escalation_notified')->default(false);

            $table->unique(['approval_request_id', 'step_number']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_requests');
    }
};
