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
        Schema::create('crisis_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('org_id')->nullable()->constrained('organizations')->onDelete('set null');
            $table->string('session_id')->index();
            $table->enum('risk_level', ['low', 'medium', 'high', 'severe'])->default('high');
            $table->timestamp('triggered_at')->useCurrent();
            $table->boolean('resources_shown')->default(false);
            $table->boolean('banner_shown')->default(false);
            $table->boolean('override_active')->default(false);
            $table->enum('status', ['pending', 'reviewed', 'resolved'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crisis_events');
    }
};
