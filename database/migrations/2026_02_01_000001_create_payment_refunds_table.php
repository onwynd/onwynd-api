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
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique()->index();
            $table->foreignId('payment_id')->constrained('payments')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Refund details
            $table->decimal('amount', 15, 2);
            $table->enum('reason', ['customer_request', 'payment_error', 'cancellation', 'duplicate_payment', 'other'])->default('customer_request');
            $table->text('notes')->nullable();

            // Status tracking
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->index();
            $table->string('gateway_refund_id', 255)->nullable()->unique();

            // Timing
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('processed_at')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['payment_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
