<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game')->default('lizard'); // extensible for future games
            $table->unsignedInteger('score');
            $table->unsignedInteger('bugs_eaten')->default(0);
            $table->unsignedInteger('max_combo')->default(0);
            $table->timestamps();

            $table->index(['game', 'score']);
            $table->index(['user_id', 'game']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_scores');
    }
};
