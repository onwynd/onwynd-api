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
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('stats_text')->nullable(); // e.g. "502 Total Conversations"
            $table->text('quote');
            $table->json('conversation_history')->nullable(); // Array of {sender: 'user'|'bot', text: string}
            $table->integer('rating')->default(5);
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
