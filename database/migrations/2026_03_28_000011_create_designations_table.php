<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Designation levels (lower = more senior):
 *
 *   1  Executive         (CEO, COO, CFO...)
 *   2  Vice President    (VP Sales, VP Marketing...)
 *   3  Director          (Director of Engineering...)
 *   4  Head / Lead       (Head of Growth...)
 *   5  Senior Manager    (Senior Marketing Manager...)
 *   6  Manager           (HR Manager, Sales Manager...)
 *   7  Senior Staff      (Senior Software Engineer...)
 *   8  Mid-level Staff   (Software Engineer...)
 *   9  Junior Staff      (Junior Designer...)
 *  10  Intern / Trainee
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('code', 30)->unique();               // e.g. SWE, SR_SWE, HR_MGR
            $table->tinyInteger('level')->default(8);           // 1 (most senior) – 10 (intern)
            $table->foreignId('department_id')->nullable()
                  ->constrained('departments')->nullOnDelete();
            $table->foreignId('reports_to_designation_id')->nullable()
                  ->constrained('designations')->nullOnDelete();
            $table->decimal('salary_band_min', 15, 2)->nullable();
            $table->decimal('salary_band_max', 15, 2)->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designations');
    }
};
