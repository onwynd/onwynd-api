<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutional_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('institutional_contracts', 'midpoint_notified_at')) {
                $table->timestamp('midpoint_notified_at')->nullable()->after('sessions_used');
            }
            if (! Schema::hasColumn('institutional_contracts', 'pre_renewal_notified_at')) {
                $table->timestamp('pre_renewal_notified_at')->nullable()->after('midpoint_notified_at');
            }
            if (! Schema::hasColumn('institutional_contracts', 'expiry_notified_at')) {
                $table->timestamp('expiry_notified_at')->nullable()->after('pre_renewal_notified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('institutional_contracts', function (Blueprint $table) {
            $columns = ['midpoint_notified_at', 'pre_renewal_notified_at', 'expiry_notified_at'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('institutional_contracts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
