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
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('price_ngn', 10, 2)->nullable()->after('price');
            $table->decimal('price_usd', 10, 2)->nullable()->after('price_ngn');
            $table->decimal('setup_fee_ngn', 10, 2)->nullable()->after('price_usd');
            $table->decimal('setup_fee_usd', 10, 2)->nullable()->after('setup_fee_ngn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['price_ngn', 'price_usd', 'setup_fee_ngn', 'setup_fee_usd']);
        });
    }
};
