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
        Schema::create('leaderboards', function (Blueprint $table) {
            $table->id();
            $table->string('week'); // e.g., "2023-W45"
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('rank');
            $table->integer('score');
            $table->string('type'); // check-ins, community_support, streak
            $table->timestamps();

            $table->index(['week', 'type']);
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaderboards');
    }
};
