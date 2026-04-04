<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_referrals', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('referrer_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('referred_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('referral_code_id')->constrained('user_referral_codes')->onDelete('cascade');
            $table->enum('referrer_tier', ['freemium', 'paid']);
            $table->enum('status', ['pending', 'referee_rewarded', 'referrer_rewarded', 'fully_rewarded', 'expired'])
                ->default('pending');
            $table->timestamp('referee_rewarded_at')->nullable();
            $table->timestamp('referrer_rewarded_at')->nullable();
            $table->timestamps();

            // One referral record per referred user
            $table->unique('referred_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_referrals');
    }
};
