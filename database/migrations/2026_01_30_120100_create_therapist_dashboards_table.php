<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('therapist_dashboards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('therapist_id')->unique()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedInteger('total_patients')->default(0);
            $table->unsignedInteger('sessions_completed_total')->default(0);
            $table->unsignedInteger('sessions_this_month')->default(0);
            $table->unsignedInteger('pending_sessions')->default(0);
            $table->decimal('total_earnings_lifetime', 15, 2)->default(0);
            $table->decimal('total_earnings_this_month', 15, 2)->default(0);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->unsignedInteger('total_ratings')->default(0);
            $table->decimal('response_time_hours', 8, 2)->nullable();
            $table->decimal('patient_satisfaction_percentage', 5, 2)->default(0);
            $table->unsignedInteger('total_hours_available_this_month')->default(0);
            $table->unsignedInteger('total_hours_booked_this_month')->default(0);
            $table->decimal('utilization_rate_this_month', 5, 4)->default(0);
            $table->decimal('avg_session_duration_minutes', 8, 2)->nullable();
            $table->json('specializations')->nullable();
            $table->json('recent_reviews')->nullable();
            $table->dateTime('last_activity_at')->nullable();
            $table->timestamps();

            $table->foreign('therapist_id')->references('id')->on('therapist_profiles')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('therapist_dashboards');
    }
};
