<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->enum('metric_type', ['user', 'therapist', 'institutional', 'admin']);
            $table->string('metric_key')->index();
            $table->json('metric_value')->nullable();
            $table->enum('period', ['daily', 'weekly', 'monthly', 'yearly'])->default('monthly');
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->json('previous_value')->nullable();
            $table->decimal('change_percentage', 8, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['metric_type', 'metric_key', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_metrics');
    }
};
