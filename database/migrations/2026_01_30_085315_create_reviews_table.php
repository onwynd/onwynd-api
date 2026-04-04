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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('therapy_sessions');
            $table->foreignId('patient_id')->constrained('users');
            $table->foreignId('therapist_id')->constrained('users');
            $table->integer('rating');
            $table->text('review_text')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_verified')->default(true);
            $table->boolean('is_published')->default(true);
            $table->integer('helpful_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
