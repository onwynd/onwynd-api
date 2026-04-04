<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('therapy_sessions', 'rating')) {
                $table->unsignedTinyInteger('rating')->nullable()->after('recording_url');
            }
            if (! Schema::hasColumn('therapy_sessions', 'review_text')) {
                $table->text('review_text')->nullable()->after('rating');
            }
            if (! Schema::hasColumn('therapy_sessions', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('review_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $columns = array_filter(
                ['rating', 'review_text', 'reviewed_at'],
                fn (string $col) => Schema::hasColumn('therapy_sessions', $col)
            );
            if ($columns) {
                $table->dropColumn(array_values($columns));
            }
        });
    }
};
