<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id', 20)->unique();   // e.g. ONW-ABC123
            $table->string('name', 191);
            $table->string('email', 191);
            $table->string('phone', 30)->nullable();
            $table->enum('subject', ['general', 'support', 'partnerships', 'press', 'other'])->default('general');
            $table->text('message');
            $table->enum('status', ['new', 'open', 'replied', 'resolved', 'spam'])->default('new');
            $table->text('internal_notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_submissions');
    }
};
