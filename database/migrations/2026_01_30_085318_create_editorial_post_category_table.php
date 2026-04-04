<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('editorial_post_category', function (Blueprint $table) {
            $table->foreignId('editorial_post_id')->constrained('editorial_posts');
            $table->foreignId('category_id')->constrained('editorial_categories');
            $table->primary(['editorial_post_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('editorial_post_category');
    }
};
