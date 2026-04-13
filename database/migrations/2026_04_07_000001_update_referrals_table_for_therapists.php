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
        Schema::table('referrals', function (Blueprint $table) {
            // Make ambassador_id nullable so therapists can use this table
            $table->foreignId('ambassador_id')->nullable()->change();
            
            // Add therapist_id column
            $table->foreignId('therapist_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Update status enum to include accepted, declined
            // Note: In some databases, we might need to use raw SQL for enum updates
            // But for this task, we'll follow the standard Laravel pattern.
            $table->string('status')->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropForeign(['therapist_id']);
            $table->dropColumn('therapist_id');
            $table->foreignId('ambassador_id')->nullable(false)->change();
        });
    }
};
