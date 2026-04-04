<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->json('emergency_contact')->nullable()->after('emergency_contact_relationship');
            $table->json('preferences')->nullable()->after('emergency_contact');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['emergency_contact', 'preferences']);
        });
    }
};
