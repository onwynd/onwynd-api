<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee record — the canonical source of truth for every staff member's
 * position within the org hierarchy. Separate from users table so it can
 * evolve independently (transfers, promotions, etc.) without touching auth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_number', 30)->unique();     // e.g. ONW-2026-0001
            $table->foreignId('department_id')->nullable()
                  ->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()
                  ->constrained('designations')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()          // Direct line manager
                  ->constrained('users')->nullOnDelete();

            $table->date('join_date');
            $table->date('probation_end_date')->nullable();
            $table->date('confirmation_date')->nullable();
            $table->date('exit_date')->nullable();

            $table->enum('contract_type', [
                'permanent', 'contract', 'internship', 'part_time', 'consultant',
            ])->default('permanent');

            $table->enum('employment_status', [
                'active', 'probation', 'on_leave', 'suspended', 'resigned', 'terminated',
            ])->default('probation');

            // Work location
            $table->enum('work_mode', ['onsite', 'remote', 'hybrid'])->default('hybrid');
            $table->string('office_location')->nullable();

            // Compensation (stored for quick access; EmployeeSalary has the history)
            $table->decimal('current_salary', 15, 2)->nullable();
            $table->string('salary_currency', 3)->default('NGN');

            $table->text('notes')->nullable();

            // Who created / last modified this record
            $table->foreignId('created_by')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                  ->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_records');
    }
};
