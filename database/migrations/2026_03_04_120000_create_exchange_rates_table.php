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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 3)->default('NGN'); // Base currency (usually NGN)
            $table->string('target_currency', 3); // Target currency (USD, GBP, EUR, etc.)
            $table->decimal('rate', 20, 10); // Exchange rate (how much of target currency equals 1 unit of base currency)
            $table->decimal('inverse_rate', 20, 10); // Inverse rate for reverse calculations
            $table->string('source', 50)->default('manual'); // Source of the rate (manual, api, bank, etc.)
            $table->boolean('is_active')->default(true); // Whether this rate is currently active
            $table->timestamp('last_updated_at')->useCurrent(); // When the rate was last updated
            $table->timestamps();

            // Indexes
            $table->index(['base_currency', 'target_currency']);
            $table->index('target_currency');
            $table->index('is_active');
            $table->index('last_updated_at');

            // Unique constraint to prevent duplicate currency pairs
            $table->unique(['base_currency', 'target_currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
