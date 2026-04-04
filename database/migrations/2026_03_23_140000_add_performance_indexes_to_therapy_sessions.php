<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // therapy_sessions indexes
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $indexes = DB::select('SHOW INDEX FROM therapy_sessions');
            $existing = collect($indexes)->pluck('Key_name')->toArray();

            if (!in_array('idx_therapist_scheduled', $existing)) {
                $table->index(['therapist_id', 'scheduled_at'], 'idx_therapist_scheduled');
            }
            if (!in_array('idx_patient_status', $existing)) {
                $table->index(['patient_id', 'status'], 'idx_patient_status');
            }
            if (!in_array('idx_status_scheduled', $existing)) {
                $table->index(['status', 'scheduled_at'], 'idx_status_scheduled');
            }
            if (Schema::hasColumn('therapy_sessions', 'anonymous_fingerprint') && !in_array('idx_anon_fingerprint', $existing)) {
                $table->index(['anonymous_fingerprint'], 'idx_anon_fingerprint');
            }
        });

        // journal_entries (if table exists)
        if (Schema::hasTable('journal_entries')) {
            $jIndexes = DB::select('SHOW INDEX FROM journal_entries');
            $jExisting = collect($jIndexes)->pluck('Key_name')->toArray();
            Schema::table('journal_entries', function (Blueprint $table) use ($jExisting) {
                if (!in_array('idx_journal_user_created', $jExisting)) {
                    $table->index(['user_id', 'created_at'], 'idx_journal_user_created');
                }
            });
        }

        // sleep_records (if table exists)
        if (Schema::hasTable('sleep_records')) {
            $sIndexes = DB::select('SHOW INDEX FROM sleep_records');
            $sExisting = collect($sIndexes)->pluck('Key_name')->toArray();
            Schema::table('sleep_records', function (Blueprint $table) use ($sExisting) {
                if (!in_array('idx_sleep_user_start', $sExisting)) {
                    $table->index(['user_id', 'start_time'], 'idx_sleep_user_start');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            $indexes = DB::select('SHOW INDEX FROM therapy_sessions');
            $existing = collect($indexes)->pluck('Key_name')->toArray();

            if (in_array('idx_therapist_scheduled', $existing)) {
                $table->dropIndex('idx_therapist_scheduled');
            }
            if (in_array('idx_patient_status', $existing)) {
                $table->dropIndex('idx_patient_status');
            }
            if (in_array('idx_status_scheduled', $existing)) {
                $table->dropIndex('idx_status_scheduled');
            }
            if (in_array('idx_anon_fingerprint', $existing)) {
                $table->dropIndex('idx_anon_fingerprint');
            }
        });

        if (Schema::hasTable('journal_entries')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                if (Schema::hasColumn('journal_entries', 'user_id')) {
                    $table->dropForeign(['user_id']); // Drop foreign key first
                }
                $jIndexes = DB::select('SHOW INDEX FROM journal_entries');
                $jExisting = collect($jIndexes)->pluck('Key_name')->toArray();
                if (in_array('idx_journal_user_created', $jExisting)) {
                    $table->dropIndex('idx_journal_user_created');
                }
            });
        }

        if (Schema::hasTable('sleep_records')) {
            Schema::table('sleep_records', function (Blueprint $table) {
                $sIndexes = DB::select('SHOW INDEX FROM sleep_records');
                $sExisting = collect($sIndexes)->pluck('Key_name')->toArray();
                if (in_array('idx_sleep_user_start', $sExisting)) {
                    $table->dropIndex('idx_sleep_user_start');
                }
            });
        }
    }
};
