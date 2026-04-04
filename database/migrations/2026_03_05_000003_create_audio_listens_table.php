<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_listens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('track_id');
            $table->string('track_title');
            $table->enum('track_category', ['mindfulness', 'affirmation', 'sleep', 'grief', 'nature', 'ambient', 'other'])->default('other');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('listened_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'listened_at']);
            $table->index(['user_id', 'track_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_listens');
    }
};
