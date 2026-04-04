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
        Schema::create('session_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('therapy_sessions');
            $table->foreignId('therapist_id')->constrained('users');
            $table->text('session_summary');
            $table->text('observations')->nullable();
            $table->text('treatment_plan')->nullable();
            $table->text('next_steps')->nullable();
            $table->text('private_notes')->nullable();
            $table->boolean('is_shared_with_patient')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_notes');
    }
};
