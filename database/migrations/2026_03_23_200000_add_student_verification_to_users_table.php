<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'student_verification_status')) {
                $table->enum('student_verification_status', ['pending', 'approved', 'rejected', 'verified'])
                    ->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('users', 'student_email')) {
                $table->string('student_email')->nullable()->after('student_verification_status');
            }
            if (!Schema::hasColumn('users', 'student_id')) {
                $table->string('student_id', 100)->nullable()->after('student_email');
            }
            if (!Schema::hasColumn('users', 'student_verified_at')) {
                $table->timestamp('student_verified_at')->nullable()->after('student_id');
            }
            if (!Schema::hasColumn('users', 'institution_name')) {
                $table->string('institution_name')->nullable()->after('student_verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = ['institution_name', 'student_verified_at', 'student_id', 'student_email', 'student_verification_status'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
