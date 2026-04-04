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
        Schema::table('patients', function (Blueprint $table) {
            $table->string('department')->nullable()->after('user_id')->comment('Patient department (e.g., Mental Health, Physical Therapy, Nutrition, Cardiology)');
            $table->enum('status', ['active', 'inactive', 'monitoring', 'critical'])->default('active')->after('department')->comment('Patient care status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['department', 'status']);
        });
    }
};
