<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_benefits', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon')->default('Heart'); // lucide-react icon name
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedInteger('enrolled_count')->default(0);
            $table->timestamps();
        });

        // Seed default benefits so the page is useful immediately
        DB::table('hr_benefits')->insert([
            ['title' => 'Health Insurance',         'description' => 'Comprehensive medical, dental, and vision coverage.',              'icon' => 'Heart',      'status' => 'active', 'enrolled_count' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Life Insurance',            'description' => 'Group life insurance for all employees.',                          'icon' => 'Shield',     'status' => 'active', 'enrolled_count' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Pension / Retirement',      'description' => 'Employee contribution matching up to 5%.',                         'icon' => 'DollarSign', 'status' => 'active', 'enrolled_count' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Annual Leave',              'description' => '21 days annual leave per year.',                                   'icon' => 'Plane',      'status' => 'active', 'enrolled_count' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Learning & Development',   'description' => 'Annual budget for courses, certifications, and conferences.',      'icon' => 'BookOpen',   'status' => 'active', 'enrolled_count' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_benefits');
    }
};
