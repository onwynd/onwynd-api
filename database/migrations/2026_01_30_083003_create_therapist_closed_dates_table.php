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
        if (Schema::hasTable('therapist_closed_dates')) {
            return;
        }
        Schema::create('therapist_closed_dates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('therapist_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason')->nullable(); // vacation, sick leave, training, etc.
            $table->text('notes')->nullable();
            $table->boolean('is_recurring')->default(false); // For annual recurring closures
            $table->string('recurrence_pattern')->nullable(); // yearly, monthly, etc.
            $table->boolean('is_removed')->default(false); // Soft delete for archival
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->foreign('therapist_id')->references('id')->on('therapist_profiles')->onDelete('cascade');
            $table->index('therapist_id');
            $table->index('start_date');
            $table->index('end_date');
            $table->index('is_removed');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapist_closed_dates');
    }
};
