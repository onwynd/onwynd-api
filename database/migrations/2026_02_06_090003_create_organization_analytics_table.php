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
        Schema::create('organization_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');

            // Usage Metrics
            $table->unsignedInteger('total_members')->default(0);
            $table->unsignedInteger('active_members_monthly')->default(0);
            $table->decimal('engagement_rate', 5, 2)->default(0); // 0-100%

            // Session Metrics
            $table->unsignedInteger('total_sessions')->default(0);
            $table->decimal('avg_sessions_per_member', 8, 2)->default(0);

            // Health/Impact Metrics
            $table->decimal('avg_wellness_score', 5, 2)->nullable(); // 1-10 or 1-100
            $table->json('concern_distribution')->nullable(); // e.g. {"anxiety": 40, "stress": 30}
            $table->unsignedInteger('at_risk_count')->default(0);
            $table->decimal('risk_reduction_rate', 5, 2)->default(0);

            // Financial/ROI (for dashboard display)
            $table->decimal('estimated_savings', 15, 2)->default(0); // based on absenteeism reduction
            $table->decimal('roi_multiplier', 8, 2)->nullable();

            // Metadata
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_analytics');
    }
};
