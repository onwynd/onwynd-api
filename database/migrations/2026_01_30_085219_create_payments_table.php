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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique()->index();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('session_id')->nullable()->constrained('therapy_sessions')->onDelete('set null');
            $table->unsignedBigInteger('subscription_id')->nullable()->index();

            // Payment details
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('NGN')->index();
            $table->string('payment_type', 50); // session_booking, subscription, consultation, etc.
            $table->text('description')->nullable();

            // Gateway information
            $table->string('payment_gateway', 50)->index();
            $table->string('payment_reference', 255)->unique()->index();
            $table->string('gateway_payment_id', 255)->nullable()->index();

            // Status tracking
            $table->enum('status', ['draft', 'pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded'])->default('draft')->index();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->index();

            // Timing
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();

            // Refund tracking
            $table->decimal('refund_amount', 15, 2)->nullable()->default(0);
            $table->timestamp('refunded_at')->nullable();

            // Gateway response and metadata
            $table->json('gateway_response')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['payment_gateway', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
