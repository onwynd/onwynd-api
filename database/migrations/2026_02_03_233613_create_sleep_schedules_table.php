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
        Schema::create('sleep_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->time('target_bedtime');
            $table->time('target_wake_time');
            $table->boolean('reminder_enabled')->default(true);
            $table->integer('reminder_minutes_before')->default(30);
            $table->json('days_of_week')->nullable()->comment('Array of days, e.g. ["Mon", "Tue"]');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sleep_schedules');
    }
};
