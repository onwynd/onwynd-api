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
        Schema::create('therapist_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique()->index();
            $table->foreignId('therapist_id')->constrained('therapist_profiles')->onDelete('cascade');

            // Payout details
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('NGN')->index();
            $table->string('payment_reason', 50); // session_payment, bonus, refund, etc.

            // Payment gateway
            $table->string('payment_gateway', 50);
            $table->string('payment_reference', 255)->unique()->index();
            $table->string('gateway_payment_id', 255)->nullable();

            // Status
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending')->index();

            // Timing
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('failure_reason')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['therapist_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['payment_gateway', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapist_payouts');
    }
};
