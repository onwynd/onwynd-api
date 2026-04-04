<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->unique(['user_id', 'type'], 'notifications_user_type_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::disableForeignKeyConstraints(); // Disable foreign key checks
            Schema::table('notifications', function (Blueprint $table) {
                if (Schema::hasIndex('notifications', 'notifications_user_type_unique')) {
                    $table->dropUnique('notifications_user_type_unique');
                }
            });
            Schema::enableForeignKeyConstraints(); // Re-enable foreign key checks
        }
    }
};
