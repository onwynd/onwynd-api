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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('plan_id')->constrained('subscription_plans');
            $table->enum('status', ['active', 'cancelled', 'expired', 'paused']);
            $table->dateTime('current_period_start');
            $table->dateTime('current_period_end');
            $table->dateTime('cancel_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('trial_ends_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
