<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->index();
            $table->boolean('email_notifications')->default(true);
            $table->boolean('sms_notifications')->default(false);
            $table->boolean('session_reminders')->default(true);
            $table->boolean('message_notifications')->default(true);
            $table->boolean('billing_notifications')->default(true);
            $table->boolean('promotional_emails')->default(false);
            $table->boolean('newsletter')->default(false);
            $table->boolean('community_updates')->default(true);
            $table->boolean('appointment_reminders')->default(true);
            $table->enum('email_frequency', ['never', 'daily', 'weekly', 'monthly'])->default('daily');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
