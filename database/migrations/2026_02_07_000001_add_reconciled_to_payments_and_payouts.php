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
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'is_reconciled')) {
                $table->boolean('is_reconciled')->default(false);
                $table->timestamp('reconciled_at')->nullable();
            }
        });

        Schema::table('payouts', function (Blueprint $table) {
            if (! Schema::hasColumn('payouts', 'is_reconciled')) {
                $table->boolean('is_reconciled')->default(false);
                $table->timestamp('reconciled_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['is_reconciled', 'reconciled_at']);
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn(['is_reconciled', 'reconciled_at']);
        });
    }
};
