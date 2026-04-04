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
        Schema::create('daily_tips', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->text('tip');
            $table->string('category')->nullable(); // anxiety, stress, mood, sleep, etc.
            $table->string('technique')->nullable(); // grounding, breathing, mindfulness, etc.
            $table->json('metadata')->nullable(); // additional context, steps, etc.
            $table->boolean('is_active')->default(true);
            $table->date('display_date')->nullable(); // specific date to show this tip
            $table->integer('usage_count')->default(0);
            $table->timestamps();

            $table->index(['display_date', 'is_active']);
            $table->index(['category', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_tips');
    }
};
