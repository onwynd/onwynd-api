<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_advisors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Professional Details
            $table->string('license_number')->unique();
            $table->enum('credential_type', ['md_psychiatrist', 'clinical_psychologist', 'licensed_counselor', 'social_worker', 'nurse_practitioner']);
            $table->string('credentials_file_path')->nullable(); // PDF of license
            $table->timestamp('license_expiry_date');

            // Specializations
            $table->json('specializations'); // ["anxiety", "depression", "trauma", "addiction"]
            $table->json('languages'); // ["english", "yoruba", "igbo"]

            // Availability
            $table->json('working_hours'); // {"monday": {"start": "09:00", "end": "17:00"}}
            $table->json('timezone'); // "Africa/Lagos"
            $table->integer('max_reviews_per_day')->default(20);

            // Performance Metrics
            $table->integer('total_reviews_completed')->default(0);
            $table->integer('critical_escalations_handled')->default(0);
            $table->decimal('average_review_time_minutes', 5, 2)->default(0);
            $table->integer('escalation_accuracy_percentage')->default(0); // 0-100
            $table->integer('therapist_satisfaction_nps')->default(0); // -100 to 100

            // Verification Status
            $table->enum('verification_status', ['pending', 'verified', 'suspended', 'rejected'])->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();

            // Supervision & Training
            $table->foreignId('supervising_director_id')->nullable()->constrained('users');
            $table->timestamp('last_training_date')->nullable();
            $table->json('training_completed')->nullable(); // ["crisis_intervention", "cultural_competency", "hipaa"]

            // Contact & Alerts
            $table->string('phone_number_primary');
            $table->string('phone_number_backup')->nullable();
            $table->string('email_primary');
            $table->string('email_backup')->nullable();
            $table->boolean('enable_sms_alerts')->default(true);
            $table->boolean('enable_push_alerts')->default(true);
            $table->boolean('enable_email_digest')->default(true);

            // Workload
            $table->integer('active_cases_monitoring')->default(0);
            $table->integer('reviews_this_month')->default(0);
            $table->integer('escalations_this_month')->default(0);
            $table->timestamp('last_active_at')->nullable();

            // Status
            $table->enum('status', ['active', 'on_leave', 'suspended', 'inactive'])->default('active');
            $table->timestamp('on_leave_until')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_advisors');
    }
};
