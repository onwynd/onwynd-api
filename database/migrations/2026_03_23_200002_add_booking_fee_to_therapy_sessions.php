<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('therapy_sessions', 'booking_fee_amount')) {
                $table->decimal('booking_fee_amount', 10, 2)->default(0)->after('session_rate');
            }
            if (!Schema::hasColumn('therapy_sessions', 'booking_fee_waived')) {
                $table->boolean('booking_fee_waived')->default(false)->after('booking_fee_amount');
            }
            if (!Schema::hasColumn('therapy_sessions', 'booking_fee_waiver_reason')) {
                $table->string('booking_fee_waiver_reason', 50)->nullable()->after('booking_fee_waived');
            }
        });
    }

    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $columns = ['booking_fee_waiver_reason', 'booking_fee_waived', 'booking_fee_amount'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('therapy_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
