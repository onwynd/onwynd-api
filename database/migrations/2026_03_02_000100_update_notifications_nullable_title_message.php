<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            DB::statement('ALTER TABLE `notifications` MODIFY `title` VARCHAR(255) NULL');
            DB::statement('ALTER TABLE `notifications` MODIFY `message` TEXT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications')) {
            DB::statement('ALTER TABLE `notifications` MODIFY `title` VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE `notifications` MODIFY `message` TEXT NOT NULL');
        }
    }
};
