<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waitlist_submissions', function (Blueprint $table) {
            // Therapist fields
            $table->integer('years_of_experience')->nullable()->after('referral_source');
            $table->string('specialty', 255)->nullable()->after('years_of_experience');

            // Institution fields
            $table->enum('institution_type', ['company', 'university', 'hospital', 'ngo'])->nullable()->after('specialty');
            $table->string('organization_name', 255)->nullable()->after('institution_type');
            $table->string('company_size', 100)->nullable()->after('organization_name');
        });
    }

    public function down(): void
    {
        Schema::table('waitlist_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'years_of_experience',
                'specialty',
                'institution_type',
                'organization_name',
                'company_size',
            ]);
        });
    }
};
