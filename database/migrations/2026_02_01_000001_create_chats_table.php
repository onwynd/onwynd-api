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
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('from_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('to_user_id')->constrained('users')->onDelete('cascade');
            $table->longText('message');
            $table->string('message_type')->default('text'); // text, image, file, etc
            $table->json('attachments')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->string('deleted_by')->nullable(); // who deleted: 'from', 'to', null
            $table->timestamp('deleted_at_from')->nullable(); // soft delete for sender
            $table->timestamp('deleted_at_to')->nullable(); // soft delete for recipient
            $table->timestamps();

            // Indexes for performance
            $table->index(['from_user_id', 'to_user_id']);
            $table->index(['to_user_id', 'is_read']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
