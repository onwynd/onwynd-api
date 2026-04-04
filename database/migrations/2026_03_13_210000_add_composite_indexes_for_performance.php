<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add composite indexes identified in go-live performance review (Section P2).
 *
 * These indexes cover the high-traffic query patterns used by:
 *  - Therapist schedule views     → therapy_sessions(therapist_id, scheduled_at, status)
 *  - Patient history views        → therapy_sessions(patient_id, status)
 *  - Notification bell badge poll → notifications(user_id, read_at)
 *  - Mood calendar queries        → mood_logs(user_id, created_at)
 *  - AI companion history         → ai_conversation_logs(user_id, created_at)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Therapist schedule view: filter by therapist + date range + status
        Schema::table('therapy_sessions', function (Blueprint $table) {
            if (! $this->hasIndex('therapy_sessions', 'therapy_sessions_therapist_scheduled_status_idx')) {
                $table->index(['therapist_id', 'scheduled_at', 'status'], 'therapy_sessions_therapist_scheduled_status_idx');
            }
            if (! $this->hasIndex('therapy_sessions', 'therapy_sessions_patient_status_idx')) {
                $table->index(['patient_id', 'status'], 'therapy_sessions_patient_status_idx');
            }
        });

        // Notification bell: unread count per user
        Schema::table('notifications', function (Blueprint $table) {
            if (! $this->hasIndex('notifications', 'notifications_user_read_at_idx')) {
                $table->index(['user_id', 'read_at'], 'notifications_user_read_at_idx');
            }
        });

        // Mood calendar: filter by user within date range
        if (Schema::hasTable('mood_logs')) {
            Schema::table('mood_logs', function (Blueprint $table) {
                if (! $this->hasIndex('mood_logs', 'mood_logs_user_created_at_idx')) {
                    $table->index(['user_id', 'created_at'], 'mood_logs_user_created_at_idx');
                }
            });
        }

        // AI conversation history: per-user chronological queries
        if (Schema::hasTable('ai_conversation_logs')) {
            Schema::table('ai_conversation_logs', function (Blueprint $table) {
                if (! $this->hasIndex('ai_conversation_logs', 'ai_conversation_logs_user_created_idx')) {
                    $table->index(['user_id', 'created_at'], 'ai_conversation_logs_user_created_idx');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('therapy_sessions', function (Blueprint $table) {
            // Drop foreign keys first if they exist
            if (Schema::hasColumn('therapy_sessions', 'therapist_id')) {
                $table->dropForeign(['therapist_id']);
            }
            if (Schema::hasColumn('therapy_sessions', 'patient_id')) {
                $table->dropForeign(['patient_id']);
            }

            // Then drop indexes
            if ($this->hasIndex('therapy_sessions', 'therapy_sessions_therapist_scheduled_status_idx')) {
                $table->dropIndex('therapy_sessions_therapist_scheduled_status_idx');
            }
            if ($this->hasIndex('therapy_sessions', 'therapy_sessions_patient_status_idx')) {
                $table->dropIndex('therapy_sessions_patient_status_idx');
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            if ($this->hasIndex('notifications', 'notifications_user_read_at_idx')) {
                $table->dropIndex('notifications_user_read_at_idx');
            }
        });

        if (Schema::hasTable('mood_logs')) {
            Schema::table('mood_logs', function (Blueprint $table) {
                if ($this->hasIndex('mood_logs', 'mood_logs_user_created_at_idx')) {
                    $table->dropIndex('mood_logs_user_created_at_idx');
                }
            });
        }

        if (Schema::hasTable('ai_conversation_logs')) {
            Schema::table('ai_conversation_logs', function (Blueprint $table) {
                if ($this->hasIndex('ai_conversation_logs', 'ai_conversation_logs_user_created_idx')) {
                    $table->dropIndex('ai_conversation_logs_user_created_idx');
                }
            });
        }
    }

    /**
     * Check if an index already exists to avoid duplicate index errors.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = \Illuminate\Support\Facades\DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );

        return count($indexes) > 0;
    }
};
