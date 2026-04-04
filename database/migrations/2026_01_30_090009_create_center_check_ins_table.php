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
        Schema::create('center_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('physical_centers');
            $table->foreignId('patient_id')->constrained('users');
            $table->foreignId('staff_id')->constrained('users');
            $table->dateTime('check_in_time');
            $table->dateTime('check_out_time')->nullable();
            $table->string('service_type', 100);
            $table->string('room_number', 50)->nullable();
            $table->json('vitals')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('center_check_ins');
    }
};
