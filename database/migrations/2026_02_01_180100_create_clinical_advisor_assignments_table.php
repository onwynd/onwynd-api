<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_advisor_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('clinical_advisor_id')->constrained('clinical_advisors')->onDelete('cascade');
            $table->foreignId('high_risk_user_id')->constrained('users')->onDelete('cascade');

            // Assignment Details
            $table->text('reason'); // "Suicide risk monitoring", "Abuse case follow-up", etc.
            $table->enum('priority', ['routine', 'urgent', 'critical'])->default('routine');
            $table->date('assigned_date');
            $table->date('review_date')->nullable();

            // Monitoring
            $table->integer('check_in_frequency_days')->default(7); // Check in every X days
            $table->timestamp('last_check_in')->nullable();
            $table->timestamp('next_check_in')->nullable();
            $table->json('monitoring_notes')->nullable();

            // Status
            $table->enum('status', ['active', 'under_observation', 'cleared', 'escalated', 'closed'])->default('active');
            $table->date('assignment_end_date')->nullable();
            $table->text('closure_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_advisor_assignments');
    }
};
