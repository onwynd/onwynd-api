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
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('payment_status'); // 'subscription', 'direct', 'free_trial'
            $table->decimal('commission_amount', 10, 2)->nullable()->after('session_rate');
            $table->decimal('commission_percentage', 5, 2)->nullable()->after('commission_amount');
            $table->boolean('is_paid_out')->default(false)->after('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'commission_amount', 'commission_percentage', 'is_paid_out']);
        });
    }
};
