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
        Schema::create('center_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('physical_centers');
            $table->string('equipment_type', 100);
            $table->string('equipment_name');
            $table->string('serial_number', 100)->nullable();
            $table->enum('status', ['available', 'in_use', 'maintenance', 'damaged'])->default('available');
            $table->date('last_maintenance')->nullable();
            $table->date('next_maintenance')->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('center_equipment');
    }
};
