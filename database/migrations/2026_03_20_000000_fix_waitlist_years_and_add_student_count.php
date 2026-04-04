<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waitlist_submissions', function (Blueprint $table) {
            $table->string('years_of_experience', 50)->nullable()->change();
            $table->string('student_count', 100)->nullable()->after('company_size');
        });
    }

    public function down(): void
    {
        Schema::table('waitlist_submissions', function (Blueprint $table) {
            $table->dropColumn('student_count');
            $table->integer('years_of_experience')->nullable()->change();
        });
    }
};
