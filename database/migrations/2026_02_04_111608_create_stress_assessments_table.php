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
        Schema::create('stress_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('stress_level'); // 1-10
            $table->json('stressors')->nullable(); // List of stressors
            $table->json('symptoms')->nullable(); // List of physical/mental symptoms
            $table->text('notes')->nullable();
            $table->string('facial_image_url')->nullable(); // For AI facial analysis
            $table->json('coping_mechanisms')->nullable(); // Suggested or used
            $table->json('ai_insights')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stress_assessments');
    }
};
