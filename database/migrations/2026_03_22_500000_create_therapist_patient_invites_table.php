<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('therapist_patient_invites', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->foreignId('therapist_id')->constrained('users')->cascadeOnDelete();
            $table->text('message')->nullable();            // personal note from therapist
            $table->timestamp('accepted_at')->nullable();   // set when patient completes signup
            $table->timestamp('expires_at')->nullable();                // 14-day window
            $table->timestamps();

            $table->index(['email', 'therapist_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('therapist_patient_invites');
    }
};
