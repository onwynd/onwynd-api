<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->foreignId('promo_code_id')
                ->nullable()
                ->after('session_rate')
                ->constrained('promotional_codes')
                ->nullOnDelete();
            $table->decimal('promo_discount_amount', 10, 2)
                ->nullable()
                ->after('promo_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $table->dropForeign(['promo_code_id']);
            $table->dropColumn(['promo_code_id', 'promo_discount_amount']);
        });
    }
};
