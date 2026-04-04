<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Specialties catalog
        Schema::create('therapist_specialties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique(); // e.g., 'Anxiety', 'Depression'
            $table->string('category')->nullable(); // e.g., 'Mood Disorders'
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Pivot table for Therapist <-> Specialties
        Schema::create('therapist_user_specialty', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Therapist ID
            $table->uuid('specialty_id');
            $table->integer('years_experience')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('specialty_id')->references('id')->on('therapist_specialties')->onDelete('cascade');
        });

        // Therapist Ratings & Feedback (for NPS/Success Rate)
        Schema::create('therapist_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('therapist_id');
            $table->unsignedBigInteger('patient_id');
            $table->uuid('session_id')->nullable(); // Optional link to specific session
            $table->integer('rating')->comment('1-5 star rating');
            $table->integer('nps_score')->nullable()->comment('0-10 Net Promoter Score');
            $table->text('feedback')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->timestamps();

            $table->foreign('therapist_id')->references('id')->on('users');
            $table->foreign('patient_id')->references('id')->on('users');
        });

        // User Matching Preferences (Stored for future use)
        Schema::create('therapist_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id'); // The patient
            $table->json('specialties')->nullable(); // Array of specialty names/IDs
            $table->string('gender_preference')->nullable(); // male, female, no_preference
            $table->json('languages')->nullable(); // Array of language codes
            $table->string('communication_style')->nullable(); // direct, empathetic, analytical
            $table->json('availability_slots')->nullable(); // Preferred days/times
            $table->integer('min_experience_years')->default(0);
            $table->integer('max_hourly_rate')->nullable();
            $table->string('timezone')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Add matching-specific columns to users table if not exists
        if (! Schema::hasColumn('users', 'languages')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('languages')->nullable(); // ['en', 'es']
                // gender column already exists in users table
                $table->string('communication_style')->nullable(); // Set by AI diagnostic
                $table->float('average_rating')->default(0);
                $table->integer('total_sessions_completed')->default(0);
                $table->integer('current_workload')->default(0); // Active patients count
                $table->integer('max_workload')->default(20);
                $table->string('timezone')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('therapist_preferences');
        Schema::dropIfExists('therapist_ratings');
        Schema::dropIfExists('therapist_user_specialty');
        Schema::dropIfExists('therapist_specialties');

        if (Schema::hasColumn('users', 'languages')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn([
                    'languages',
                    'communication_style',
                    'average_rating',
                    'total_sessions_completed',
                    'current_workload',
                    'max_workload',
                    'timezone',
                ]);
            });
        }
    }
};
