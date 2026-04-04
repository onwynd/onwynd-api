<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'country')) {
                $table->string('country', 100)->nullable()->after('contact_email');
            }
            if (! Schema::hasColumn('organizations', 'city')) {
                $table->string('city', 100)->nullable()->after('country');
            }
            if (! Schema::hasColumn('organizations', 'total_employees')) {
                $table->unsignedInteger('total_employees')->nullable()->after('city')
                    ->comment('Total declared headcount / student body');
            }
            if (! Schema::hasColumn('organizations', 'onboarded_count')) {
                $table->unsignedInteger('onboarded_count')->default(0)->after('total_employees')
                    ->comment('Members currently active on the platform');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['country', 'city', 'total_employees', 'onboarded_count']);
        });
    }
};
