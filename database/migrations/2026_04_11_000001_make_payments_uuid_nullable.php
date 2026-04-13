<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_uuid_unique');
            $table->string('uuid', 36)->nullable()->change();
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_uuid_unique');
            $table->string('uuid', 36)->nullable(false)->change();
            $table->unique('uuid');
        });
    }
};
