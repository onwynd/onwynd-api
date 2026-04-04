<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            if (! Schema::hasColumn('payouts', 'type')) {
                $table->string('type')->default('session')->after('currency');
                // Values: 'session' (session earnings), 'stipend' (founding stipend), 'earnings' (generic)
            }
            if (! Schema::hasColumn('payouts', 'description')) {
                $table->text('description')->nullable()->after('account_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $cols = array_filter(
                ['type', 'description'],
                fn ($c) => Schema::hasColumn('payouts', $c)
            );
            if ($cols) {
                $table->dropColumn(array_values($cols));
            }
        });
    }
};
