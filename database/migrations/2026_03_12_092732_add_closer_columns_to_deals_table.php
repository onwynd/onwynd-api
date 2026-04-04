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
        Schema::table('deals', function (Blueprint $table) {
            if (! Schema::hasColumn('deals', 'closer_id')) {
                $table->foreignId('closer_id')->nullable()->constrained('users')->onDelete('set null');
            }
            if (! Schema::hasColumn('deals', 'lost_reason')) {
                $table->string('lost_reason')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropForeign(['closer_id']);
            $table->dropColumn(['closer_id', 'lost_reason']);
        });
    }
};
