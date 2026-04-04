<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quota_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('free_daily_activities')->default(2);
            $table->unsignedInteger('free_ai_messages')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_settings');
    }
};
