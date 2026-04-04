<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mailable_class', 255)->nullable();   // e.g. App\Mail\WaitlistInviteEmail
            $table->string('recipient', 255);                    // to address
            $table->string('subject', 500)->nullable();
            $table->enum('status', ['sent', 'failed'])->default('sent');
            $table->text('failure_reason')->nullable();          // exception message on failure
            $table->json('metadata')->nullable();                // extra context (user_id, type, etc.)
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('recipient');
            $table->index('mailable_class');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};
