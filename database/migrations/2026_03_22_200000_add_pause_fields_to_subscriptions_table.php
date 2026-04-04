<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'paused_at')) {
                $table->dateTime('paused_at')->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('subscriptions', 'paused_until')) {
                $table->dateTime('paused_until')->nullable()->after('paused_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['paused_at', 'paused_until']);
        });
    }
};
