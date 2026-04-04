<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Department hierarchy (self-referential):
 *
 *   Onwynd (root)
 *   ├── Clinical
 *   │   ├── Therapy
 *   │   └── Wellness
 *   ├── Technology
 *   │   ├── Engineering
 *   │   └── Product
 *   ├── Business
 *   │   ├── Sales
 *   │   ├── Marketing
 *   │   └── Finance
 *   └── Operations
 *       ├── HR
 *       └── Support
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();               // e.g. SALES, MKT, ENG
            $table->text('description')->nullable();
            $table->foreignId('head_user_id')->nullable()       // Dept head (employee)
                  ->constrained('users')->nullOnDelete();
            $table->foreignId('parent_department_id')->nullable()
                  ->constrained('departments')->nullOnDelete();  // Sub-departments
            $table->integer('headcount')->default(0);            // Cached, updated by trigger/observer
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
