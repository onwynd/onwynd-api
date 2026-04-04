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
        Schema::create('scheduled_emails', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('email_type'); // session_reminder, invoice, welcome, etc.
            $table->json('data'); // Email data/variables
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('pending'); // pending, sent, failed, canceled
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('is_template')->default(false);
            $table->string('template_name')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index('recipient_email');
            $table->index('email_type');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_emails');
    }
};
