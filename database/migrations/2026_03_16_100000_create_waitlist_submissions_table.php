<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->enum('role', ['patient', 'therapist', 'institution', 'other'])->default('patient');
            $table->string('country', 100)->nullable();
            $table->string('referral_source', 100)->nullable(); // how did you hear about us
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending'); // pending, invited, declined
            $table->timestamp('invited_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_submissions');
    }
};
