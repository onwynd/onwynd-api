<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_session_participants', function (Blueprint $table) {
            // Neutral partner role for couple therapy sessions.
            // partner_1 / partner_2 are purely positional — no hierarchy implied.
            $table->enum('couple_role', ['partner_1', 'partner_2'])->nullable()->after('role_in_session');
        });
    }

    public function down(): void
    {
        Schema::table('group_session_participants', function (Blueprint $table) {
            $table->dropColumn('couple_role');
        });
    }
};
