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
        // 1. Prescriptions Table
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('doctor_id')->constrained('users'); // Therapist/Doctor
            $table->foreignId('patient_id')->constrained('users');
            $table->string('medication_name');
            $table->string('dosage'); // e.g., "10mg"
            $table->string('frequency'); // e.g., "Twice daily"
            $table->string('duration')->nullable(); // e.g., "7 days"
            $table->text('instructions')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->string('digital_signature')->nullable(); // For EPSC compliance
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Medication Logs (Patient Tracking)
        Schema::create('medication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('prescription_id')->nullable()->constrained('prescriptions');
            $table->string('medication_name'); // Allow manual entry even without prescription
            $table->string('dosage_taken');
            $table->timestamp('taken_at');
            $table->text('notes')->nullable(); // Side effects, etc.
            $table->integer('mood_rating')->nullable(); // 1-5, how they felt after
            $table->boolean('skipped')->default(false);
            $table->string('skip_reason')->nullable();
            $table->timestamps();
        });

        // 3. Secure Documents (File Sharing)
        Schema::create('secure_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users'); // Uploader
            $table->foreignId('shared_with_id')->nullable()->constrained('users'); // Specific recipient
            $table->string('title');
            $table->string('file_path');
            $table->string('file_type'); // pdf, jpg, etc.
            $table->bigInteger('file_size'); // bytes
            $table->boolean('is_encrypted')->default(true); // Flag for compliance
            $table->string('encryption_key_id')->nullable(); // If using KMS
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Session Participants (Group/Couples Therapy)
        Schema::create('session_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('therapy_sessions')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('role', ['patient', 'therapist', 'partner', 'family_member', 'observer'])->default('patient');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->string('status')->default('invited'); // invited, joined, declined
            $table->timestamps();

            $table->unique(['session_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_participants');
        Schema::dropIfExists('secure_documents');
        Schema::dropIfExists('medication_logs');
        Schema::dropIfExists('prescriptions');
    }
};
