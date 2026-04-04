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
        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments');
            $table->text('question_text');
            $table->enum('question_type', ['multiple_choice', 'scale', 'text', 'yes_no']);
            $table->json('options')->nullable();
            $table->integer('scale_min')->nullable();
            $table->integer('scale_max')->nullable();
            $table->json('scale_labels')->nullable();
            $table->integer('order_number');
            $table->boolean('is_required')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_questions');
    }
};
