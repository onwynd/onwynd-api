<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // true  = user has explicitly set a password (email/password sign-in works)
            // false = Google/social-only account (no password credential)
            // Default true so existing users are not affected.
            $table->boolean('has_password')->default(true)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('has_password');
        });
    }
};
