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
        if (Schema::hasTable('mood_logs')) {
            return;
        }
        Schema::create('mood_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->unsignedTinyInteger('mood_score')->nullable();
            $table->json('emotions')->nullable();
            $table->text('notes')->nullable();
            $table->json('activities')->nullable();
            $table->decimal('sleep_hours', 4, 2)->nullable();
            $table->json('weather_data')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mood_logs');
    }
};
