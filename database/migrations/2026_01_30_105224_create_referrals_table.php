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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('ambassador_id')->constrained('ambassadors');
            $table->foreignId('referred_user_id')->constrained('users');
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->decimal('amount', 10, 2)->nullable(); // Commission amount earned
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
