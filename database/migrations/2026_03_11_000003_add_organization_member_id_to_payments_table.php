<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_member_id')->nullable()->after('session_id');
            $table->index('organization_member_id');

            // Add foreign key constraint if organization_members table exists
            if (Schema::hasTable('organization_members')) {
                $table->foreign('organization_member_id')
                    ->references('id')
                    ->on('organization_members')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['organization_member_id']);
            $table->dropColumn('organization_member_id');
        });
    }
};
