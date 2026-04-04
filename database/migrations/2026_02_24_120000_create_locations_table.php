<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->enum('type', ['continent', 'country', 'region', 'state', 'province', 'city', 'lga', 'town', 'area', 'street'])->default('city');
            $table->foreignId('parent_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('country_code', 2)->nullable()->index(); // ISO 3166-1 alpha-2
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'parent_id']);
            $table->index(['country_code', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
