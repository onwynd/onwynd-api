<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_territories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code')->unique()->nullable();
            // Territory type hierarchy: region > zone > state > city > lga > area > school
            $table->enum('type', ['region', 'zone', 'state', 'city', 'lga', 'area', 'school', 'custom'])->default('zone');
            $table->foreignId('parent_id')->nullable()->constrained('sales_territories')->nullOnDelete();
            $table->string('country')->default('Nigeria');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'parent_id']);
        });

        Schema::create('sales_agent_territories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('territory_id')->constrained('sales_territories')->onDelete('cascade');
            $table->enum('role', [
                'zone_manager',
                'regional_manager',
                'city_agent',
                'school_agent',
                'area_agent',
                'lga_agent',
                'territory_lead',
                'sales_rep',
            ])->default('sales_rep');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'territory_id']);
            $table->index(['user_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_agent_territories');
        Schema::dropIfExists('sales_territories');
    }
};
