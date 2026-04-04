<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the base salary configuration for each Onwynd internal employee.
 * These figures feed directly into the Finance Statements (income statement &
 * cash flow) as operating expenses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('base_salary', 14, 2);        // Monthly gross salary
            $table->string('currency', 3)->default('NGN');
            $table->string('role_label')->nullable();     // e.g. "Head of Engineering"
            $table->string('department')->nullable();     // e.g. "Engineering", "Sales"
            $table->date('effective_from');
            $table->date('effective_to')->nullable();     // NULL = currently active
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Only one active record per user at a time
            $table->index(['user_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salaries');
    }
};
