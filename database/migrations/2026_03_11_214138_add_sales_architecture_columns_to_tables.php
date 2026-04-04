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
        // Update leads table
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'owner_id')) {
                $table->foreignId('owner_id')->nullable()->constrained('users')->onDelete('set null');
            }
            if (! Schema::hasColumn('leads', 'handoff_note')) {
                $table->text('handoff_note')->nullable();
            }
            if (! Schema::hasColumn('leads', 'handed_off_at')) {
                $table->timestamp('handed_off_at')->nullable();
            }
            if (! Schema::hasColumn('leads', 'handed_off_by')) {
                $table->foreignId('handed_off_by')->nullable()->constrained('users')->onDelete('set null');
            }
        });

        // Update deals table
        Schema::table('deals', function (Blueprint $table) {
            if (! Schema::hasColumn('deals', 'owner_id')) {
                $table->foreignId('owner_id')->nullable()->constrained('users')->onDelete('set null');
            }
        });

        // Update organizations table
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'relationship_manager_id')) {
                $table->foreignId('relationship_manager_id')->nullable()->constrained('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'owner_id')) {
                $table->dropForeign(['owner_id']);
            }
            if (Schema::hasColumn('leads', 'handed_off_by')) {
                $table->dropForeign(['handed_off_by']);
            }
            $table->dropColumn(['owner_id', 'handoff_note', 'handed_off_at', 'handed_off_by']);
        });

        Schema::table('deals', function (Blueprint $table) {
            if (Schema::hasColumn('deals', 'owner_id')) {
                $table->dropForeign(['owner_id']);
            }
            $table->dropColumn('owner_id');
        });

        Schema::table('organizations', function (Blueprint $table) {
            if (Schema::hasColumn('organizations', 'relationship_manager_id')) {
                $table->dropForeign(['relationship_manager_id']);
            }
            $table->dropColumn('relationship_manager_id');
        });
    }
};
