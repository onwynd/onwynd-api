<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_settings', function (Blueprint $table) {
            $table->id();
            // Which role these settings apply to: therapist | employee
            $table->enum('role', ['therapist', 'employee'])->unique();
            // Day of month payouts are processed (1–28 to avoid month-end issues)
            $table->unsignedTinyInteger('payout_day')->default(15);
            // Minimum balance required to trigger payout
            $table->unsignedInteger('minimum_amount_kobo')->default(500000); // ₦5,000 in kobo
            // Currency (NGN, USD, etc.)
            $table->string('currency', 10)->default('NGN');
            // Disbursement provider: paystack | lenco | manual
            $table->string('provider', 50)->default('paystack');
            // Human-readable cycle description shown in UI
            $table->string('cycle_description', 255)->default('Monthly — 15th of each month');
            // Auto-process or require admin click
            $table->boolean('auto_process')->default(false);
            $table->timestamps();
        });

        // Seed defaults
        \Illuminate\Support\Facades\DB::table('payout_settings')->insert([
            ['role' => 'therapist', 'payout_day' => 15, 'minimum_amount_kobo' => 500000,  'currency' => 'NGN', 'provider' => 'paystack', 'cycle_description' => 'Monthly — 15th of each month', 'auto_process' => false, 'created_at' => now(), 'updated_at' => now()],
            ['role' => 'employee',  'payout_day' => 25, 'minimum_amount_kobo' => 1000000, 'currency' => 'NGN', 'provider' => 'lenco',    'cycle_description' => 'Monthly — 25th of each month (salary run)', 'auto_process' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_settings');
    }
};
