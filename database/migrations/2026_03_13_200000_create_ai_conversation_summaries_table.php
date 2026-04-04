<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversation_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('session_id')->index(); // AI conversation/session UUID
            $table->text('summary');               // Plain-text summary of the message window
            $table->unsignedSmallInteger('message_count')->default(10); // How many messages this summary covers
            $table->unsignedBigInteger('last_message_id')->nullable();  // AIChat.id of the last message summarised
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Index for fetching last N summaries per user efficiently
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_summaries');
    }
};
