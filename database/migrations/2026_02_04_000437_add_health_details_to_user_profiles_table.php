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
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('mbti_type')->nullable();
            $table->float('height_cm')->nullable();
            $table->float('weight_kg')->nullable();
            $table->integer('daily_step_goal')->default(10000);
            $table->integer('onwynd_score_cache')->nullable()->comment('Cached latest score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['mbti_type', 'height_cm', 'weight_kg', 'daily_step_goal', 'onwynd_score_cache']);
        });
    }
};
