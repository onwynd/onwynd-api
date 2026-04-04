<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->after('remember_token');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->timestamp('last_login_at')->nullable()->after('last_seen_at');
            $table->boolean('marked_for_deletion')->default(false)->after('deleted_at');
            $table->timestamp('deletion_requested_at')->nullable()->after('marked_for_deletion');
            $table->timestamp('deletion_scheduled_at')->nullable()->after('deletion_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_enabled',
                'two_factor_secret',
                'last_login_at',
                'marked_for_deletion',
                'deletion_requested_at',
                'deletion_scheduled_at',
            ]);
        });
    }
};
