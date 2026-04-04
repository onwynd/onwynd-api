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
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('frequency'); // daily, weekly, custom
            $table->integer('target_count')->default(1); // e.g. 2 times a day
            $table->json('reminder_times')->nullable(); // e.g. ["09:00", "20:00"]
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('category')->nullable(); // e.g. health, productivity
            $table->integer('streak')->default(0);
            $table->integer('longest_streak')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('habits');
    }
};
