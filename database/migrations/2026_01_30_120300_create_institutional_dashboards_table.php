<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutional_dashboards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id')->unique()->index();
            $table->enum('institution_type', ['corporate', 'university', 'ngo', 'faith_org'])->default('corporate');
            $table->unsignedInteger('total_users')->default(0);
            $table->unsignedInteger('active_users_this_month')->default(0);
            $table->decimal('engagement_rate', 5, 4)->default(0);
            $table->unsignedInteger('total_sessions_completed')->default(0);
            $table->unsignedInteger('sessions_this_month')->default(0);
            $table->decimal('avg_session_frequency', 8, 2)->default(0);
            $table->json('health_metrics')->nullable();
            $table->decimal('average_wellness_score', 5, 2)->nullable();
            $table->json('concern_breakdown')->nullable();
            $table->unsignedInteger('at_risk_users')->default(0);
            $table->decimal('intervention_success_rate', 5, 4)->default(0);
            $table->decimal('total_investment', 15, 2)->default(0);
            $table->decimal('cost_per_user', 10, 2)->default(0);
            $table->decimal('estimated_roi', 10, 2)->nullable();
            $table->json('absenteeism_impact')->nullable();
            $table->json('satisfaction_scores')->nullable();
            $table->json('top_concerns')->nullable();
            $table->dateTime('contract_start_date')->nullable();
            $table->dateTime('contract_end_date')->nullable();
            $table->enum('contract_status', ['active', 'expired', 'renewal_due', 'prospect'])->default('prospect');
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('physical_centers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutional_dashboards');
    }
};
