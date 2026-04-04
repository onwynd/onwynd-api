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
        Schema::create('user_assessment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('assessment_id')->constrained('assessments');
            $table->json('answers');
            $table->decimal('total_score', 10, 2);
            $table->text('interpretation');
            $table->enum('severity_level', ['minimal', 'mild', 'moderate', 'severe', 'very_severe'])->nullable();
            $table->json('recommendations')->nullable();
            $table->boolean('is_shared_with_therapist')->default(false);
            $table->foreignId('shared_with_therapist_id')->nullable()->constrained('users');
            $table->timestamp('completed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_assessment_results');
    }
};
