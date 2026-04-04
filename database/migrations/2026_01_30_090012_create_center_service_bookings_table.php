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
        Schema::create('center_service_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('center_id')->constrained('physical_centers');
            $table->foreignId('service_id')->constrained('center_services');
            $table->foreignId('patient_id')->constrained('users');
            $table->foreignId('therapist_id')->nullable()->constrained('users');
            $table->dateTime('scheduled_at');
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show']);
            $table->string('room_number', 50)->nullable();
            $table->json('equipment_used')->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'refunded']);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('center_service_bookings');
    }
};
