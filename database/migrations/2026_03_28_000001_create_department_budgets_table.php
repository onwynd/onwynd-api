<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Budget approval state machine:
 *
 *   draft ──▶ pending_coo ──▶ pending_ceo ──▶ pending_finance ──▶ approved
 *     │              │               │                │
 *     └──────────────┴───────────────┴────────────────┴──▶ rejected
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('department');          // e.g. 'marketing', 'sales'
            $table->string('category');            // e.g. 'digital_ads', 'events', 'tools'
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount_requested', 15, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('period');              // e.g. 'Q1-2026', '2026-04'

            // State machine
            $table->enum('status', [
                'draft', 'pending_coo', 'pending_ceo', 'pending_finance', 'approved', 'rejected',
            ])->default('draft');

            // Actors
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by_coo')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_ceo')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_finance')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();

            // Approval trail
            $table->text('coo_notes')->nullable();
            $table->text('ceo_notes')->nullable();
            $table->text('finance_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('coo_reviewed_at')->nullable();
            $table->timestamp('ceo_reviewed_at')->nullable();
            $table->timestamp('finance_reviewed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_budgets');
    }
};
