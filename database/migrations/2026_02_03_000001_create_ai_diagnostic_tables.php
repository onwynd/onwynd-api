<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_diagnostics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->uuid('session_id')->unique(); // Conversation ID
            $table->string('status')->default('in_progress'); // in_progress, completed, escalated
            $table->string('current_stage')->default('greeting');
            $table->string('risk_level')->default('low');
            $table->integer('risk_score')->default(0);
            $table->json('summary')->nullable(); // Final AI summary
            $table->json('recommended_actions')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ai_diagnostic_id');
            $table->string('role'); // user, assistant, system
            $table->text('content');
            $table->json('metadata')->nullable(); // Token usage, model used, etc.
            $table->timestamps();

            $table->foreign('ai_diagnostic_id')->references('id')->on('ai_diagnostics')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_diagnostics');
    }
};
