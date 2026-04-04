<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'display_name')) {
                $table->string('display_name')->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('users', 'preferred_language')) {
                $table->string('preferred_language')->default('English')->after('language');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = array_filter(
                ['display_name', 'preferred_language'],
                fn ($c) => Schema::hasColumn('users', $c)
            );
            if ($cols) {
                $table->dropColumn(array_values($cols));
            }
        });
    }
};
