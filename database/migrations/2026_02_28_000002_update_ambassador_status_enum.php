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
        Schema::table('ambassadors', function (Blueprint $table) {
            // Modify the status enum to include 'pending'
            $table->enum('status', ['active', 'inactive', 'suspended', 'pending'])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ambassadors', function (Blueprint $table) {
            // Revert back to original enum values
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->change();
        });
    }
};
