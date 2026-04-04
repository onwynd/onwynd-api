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
        Schema::create('therapist_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('license_number', 100)->unique();
            $table->string('license_state', 100);
            $table->date('license_expiry');
            $table->json('specializations');
            $table->json('qualifications');
            $table->json('languages');
            $table->integer('experience_years');
            $table->decimal('hourly_rate', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->text('bio');
            $table->string('video_intro_url')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_accepting_clients')->default(true);
            $table->json('verification_documents')->nullable();
            $table->decimal('rating_average', 3, 2)->default(0.00);
            $table->integer('total_sessions')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapist_profiles');
    }
};
