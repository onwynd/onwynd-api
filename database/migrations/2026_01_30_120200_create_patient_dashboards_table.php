<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_dashboards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->index();
            $table->unsignedTinyInteger('current_mood')->nullable();
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->unsignedInteger('sessions_completed')->default(0);
            $table->unsignedInteger('sessions_this_month')->default(0);
            $table->unsignedInteger('pending_sessions_booked')->default(0);
            $table->string('primary_concern')->nullable();
            $table->json('active_goals')->nullable();
            $table->json('goal_progress')->nullable();
            $table->unsignedTinyInteger('overall_progress')->default(0);
            $table->unsignedInteger('ai_check_ins')->default(0);
            $table->unsignedInteger('peer_messages_sent')->default(0);
            $table->unsignedInteger('meditation_minutes')->default(0);
            $table->unsignedInteger('community_participations')->default(0);
            $table->enum('subscription_status', ['free', 'premium', 'recovery_program'])->default('free');
            $table->dateTime('subscription_expires_at')->nullable();
            $table->json('mood_history')->nullable();
            $table->json('insight_tags')->nullable();
            $table->decimal('engagement_score', 5, 2)->default(0);
            $table->dateTime('last_session_at')->nullable();
            $table->dateTime('next_session_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_dashboards');
    }
};
