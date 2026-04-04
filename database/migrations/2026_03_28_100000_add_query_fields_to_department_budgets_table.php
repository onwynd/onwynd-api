<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the budget state machine with a CEO query loop:
 *
 *   … pending_ceo ──▶ queried ──▶ [creator responds] ──▶ pending_ceo (CEO re-reviews)
 *                         │
 *                         └──▶ rejected (CEO can also reject from queried)
 *
 * New fields:
 *   ceo_query_notes      — CEO's query message / reasoning
 *   ceo_suggested_amount — CEO's counter-proposal amount
 *   creator_response     — original submitter's reply
 *   creator_responded_at — when the reply was submitted
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('department_budgets', function (Blueprint $table) {
            $table->text('ceo_query_notes')->nullable()->after('ceo_notes');
            $table->decimal('ceo_suggested_amount', 15, 2)->nullable()->after('ceo_query_notes');
            $table->text('creator_response')->nullable()->after('ceo_suggested_amount');
            $table->timestamp('creator_responded_at')->nullable()->after('creator_response');
        });

        // Add 'queried' to the status enum.
        // Must be done with raw SQL since Blueprint::enum() cannot modify existing columns.
        DB::statement(
            "ALTER TABLE department_budgets
             MODIFY COLUMN status
             ENUM('draft','pending_coo','pending_ceo','queried','pending_finance','approved','rejected')
             DEFAULT 'draft'"
        );
    }

    public function down(): void
    {
        // Remove the queried option (safe only when no rows have status='queried')
        DB::statement(
            "ALTER TABLE department_budgets
             MODIFY COLUMN status
             ENUM('draft','pending_coo','pending_ceo','pending_finance','approved','rejected')
             DEFAULT 'draft'"
        );

        Schema::table('department_budgets', function (Blueprint $table) {
            $table->dropColumn(['ceo_query_notes', 'ceo_suggested_amount', 'creator_response', 'creator_responded_at']);
        });
    }
};
