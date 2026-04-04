<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('therapist_location_mismatches')) {
            return;
        }

        Schema::create('therapist_location_mismatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')->constrained('therapist_profiles')->cascadeOnDelete();
            $table->string('stored_country', 2);
            $table->string('detected_country', 2)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('detected_at');
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['therapist_id', 'resolved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('therapist_location_mismatches');
    }
};
