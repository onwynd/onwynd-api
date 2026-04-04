<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotional_codes', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('code', 50)->unique();
            $table->string('description')->nullable();
            $table->enum('type', ['percentage', 'fixed']);
            $table->decimal('discount_value', 10, 2);
            $table->string('currency', 3)->nullable();
            $table->integer('max_uses')->nullable();
            $table->integer('uses_count')->default(0);
            $table->integer('max_uses_per_user')->nullable();
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->enum('applies_to', ['session', 'subscription', 'all'])->default('all');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('promotional_code_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotional_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('therapy_sessions')->nullOnDelete();
            $table->decimal('discount_applied', 10, 2);
            $table->timestamps();
            $table->index(['promotional_code_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotional_code_usages');
        Schema::dropIfExists('promotional_codes');
    }
};
