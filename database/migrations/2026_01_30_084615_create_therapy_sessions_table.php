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
        Schema::create('therapy_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('patient_id')->constrained('users');
            $table->foreignId('therapist_id')->constrained('users');
            $table->enum('session_type', ['video', 'audio', 'chat']);
            $table->enum('status', ['scheduled', 'ongoing', 'completed', 'cancelled', 'no_show']);
            $table->dateTime('scheduled_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->integer('duration_minutes')->default(50);
            $table->decimal('session_rate', 10, 2);
            $table->enum('payment_status', ['pending', 'paid', 'refunded', 'failed']);
            $table->text('booking_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->dateTime('cancelled_at')->nullable();
            $table->string('meeting_url')->nullable();
            $table->string('room_id', 100)->nullable();
            $table->string('recording_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapy_sessions');
    }
};
