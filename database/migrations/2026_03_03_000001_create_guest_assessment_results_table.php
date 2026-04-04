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
        Schema::create('guest_assessment_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assessment_id');
            $table->string('guest_token')->unique();
            $table->json('answers');
            $table->integer('total_score');
            $table->integer('percentage');
            $table->string('severity_level');
            $table->text('interpretation');
            $table->json('recommendations');
            $table->unsignedBigInteger('linked_user_id')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('assessment_id')->references('id')->on('assessments')->onDelete('cascade');
            $table->foreign('linked_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('guest_token');
            $table->index('linked_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_assessment_results');
    }
};
