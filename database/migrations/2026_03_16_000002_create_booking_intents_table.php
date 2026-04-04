<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('booking_intents')) {
            if (!$this->indexExists('booking_intents', 'bi_user_completed_abandoned_idx')) {
                Schema::table('booking_intents', function (Blueprint $table) {
                    $table->index(['user_id', 'completed_at', 'abandoned_email_sent_at'], 'bi_user_completed_abandoned_idx');
                });
            }

            return;
        }

        Schema::create('booking_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('therapist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('context')->default('general'); // 'therapist', 'group', 'general'
            $table->string('stage')->default('page_view'); // page_view → therapist_selected → payment_initiated → completed
            $table->string('return_url')->nullable();       // exact page user was on
            $table->string('therapist_name')->nullable();   // denormalised for email rendering
            $table->timestamp('abandoned_email_sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'completed_at', 'abandoned_email_sent_at'], 'bi_user_completed_abandoned_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_intents');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->limit(1)
            ->exists();
    }
};
