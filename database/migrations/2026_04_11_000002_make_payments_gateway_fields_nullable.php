<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // payment_gateway and payment_reference are set by PaymentProcessor
            // *after* the initial draft record is created, so they must be nullable.
            $table->string('payment_gateway', 50)->nullable()->change();

            $table->dropUnique('payments_payment_reference_unique');
            $table->string('payment_reference', 255)->nullable()->change();
            $table->unique('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_gateway', 50)->nullable(false)->change();

            $table->dropUnique('payments_payment_reference_unique');
            $table->string('payment_reference', 255)->nullable(false)->change();
            $table->unique('payment_reference');
        });
    }
};
