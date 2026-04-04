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
        Schema::create('mindful_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_category_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->enum('type', ['article', 'video', 'audio']);
            $table->longText('content')->nullable(); // For articles or transcripts
            $table->string('media_url')->nullable(); // For video/audio
            $table->string('thumbnail_url')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->boolean('is_premium')->default(false);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->enum('status', ['draft', 'pending', 'published', 'rejected'])->default('draft');
            $table->text('admin_note')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mindful_resources');
    }
};
