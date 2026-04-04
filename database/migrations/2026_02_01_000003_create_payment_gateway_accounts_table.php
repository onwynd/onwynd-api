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
        Schema::create('payment_gateway_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('therapist_id')->nullable()->constrained('therapist_profiles')->onDelete('cascade');

            // Gateway account details
            $table->string('gateway', 50)->index(); // paystack, flutterwave, stripe
            $table->string('gateway_account_id', 255)->nullable();
            $table->string('email', 255);

            // Bank account details for payouts
            $table->string('account_number', 50)->nullable();
            $table->string('bank_code', 50)->nullable();
            $table->string('account_name', 255)->nullable();
            $table->string('account_type', 50)->nullable(); // savings, current
            $table->string('bvn', 20)->nullable(); // Bank Verification Number (Nigeria)

            // Account verification
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->json('verification_details')->nullable();

            // Account status
            $table->enum('status', ['active', 'inactive', 'suspended', 'deleted'])->default('active')->index();
            $table->boolean('is_primary')->default(false);

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['gateway', 'email']);
            $table->index(['user_id', 'gateway']);
            $table->index(['therapist_id', 'gateway']);
            $table->unique(['user_id', 'gateway', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_accounts');
    }
};
