<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Objectives (3-level: Company → Team, via parent_id) ──────────────────
        Schema::create('okr_objectives', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('quarter', 10);                                   // e.g. Q2-2026
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->foreignId('parent_id')->nullable()->constrained('okr_objectives')->nullOnDelete();
            $table->string('department')->nullable();                        // marketing|sales|product|hr|finance|tech|clinical
            $table->timestamps();

            $table->index(['quarter', 'status']);
            $table->index('owner_id');
            $table->index('parent_id');
        });

        // ── Key Results ──────────────────────────────────────────────────────────
        Schema::create('okr_key_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objective_id')->constrained('okr_objectives')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('metric_key')->nullable();                        // maps to DashboardMetric.metric_key
            $table->enum('metric_type', ['auto', 'manual'])->default('manual');
            $table->string('unit', 20)->default('count');                   // %, $, users, sessions, count …
            $table->decimal('start_value', 15, 4)->default(0);
            $table->decimal('current_value', 15, 4)->default(0);
            $table->decimal('target_value', 15, 4);
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->date('due_date');
            $table->enum('health_status', ['on_track', 'at_risk', 'off_track'])->default('on_track');
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamps();

            $table->index('objective_id');
            $table->index(['metric_key', 'metric_type']);
            $table->index('health_status');
            $table->index('owner_id');
        });

        // ── Initiatives (tasks/projects that drive KR progress) ─────────────────
        Schema::create('okr_initiatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('key_result_id')->constrained('okr_key_results')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'blocked'])->default('not_started');
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->index('key_result_id');
            $table->index('owner_id');
        });

        // ── Check-ins (progress snapshots — automated or manual) ────────────────
        Schema::create('okr_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('key_result_id')->constrained('okr_key_results')->cascadeOnDelete();
            $table->decimal('value', 15, 4);
            $table->text('note')->nullable();
            $table->boolean('is_automated')->default(false);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['key_result_id', 'recorded_at']);
        });

        // ── Alerts (health transition history) ──────────────────────────────────
        Schema::create('okr_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('key_result_id')->constrained('okr_key_results')->cascadeOnDelete();
            $table->enum('alert_type', ['drifted_amber', 'drifted_red', 'recovered']);
            $table->string('previous_health', 20);
            $table->string('new_health', 20);
            $table->json('notified_via')->nullable();
            $table->timestamps();

            $table->index('key_result_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('okr_alerts');
        Schema::dropIfExists('okr_check_ins');
        Schema::dropIfExists('okr_initiatives');
        Schema::dropIfExists('okr_key_results');
        Schema::dropIfExists('okr_objectives');
    }
};
