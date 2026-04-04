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
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->text('content')->nullable(); // Nullable for voice entries initially
            $table->string('type')->default('text'); // text, voice
            $table->string('audio_url')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('mood_emoji')->nullable();
            $table->integer('stress_level')->nullable(); // 1-10
            $table->json('emotions')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_private')->default(true);
            $table->json('ai_analysis')->nullable(); // Sentiment, keywords, etc.
            $table->boolean('crisis_detected')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
