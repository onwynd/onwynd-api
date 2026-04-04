<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_email_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('sequence_key'); // e.g. welcome, day3_checkin, week1_summary
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->default('pending'); // pending | sent | skipped
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'sequence_key']);
            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_email_sequences');
    }
};
