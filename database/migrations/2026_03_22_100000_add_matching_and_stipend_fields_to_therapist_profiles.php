<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('therapist_profiles')) {
            return;
        }

        Schema::table('therapist_profiles', function (Blueprint $table) {
            // ─── International / Matching fields ──────────────────────────────
            if (! Schema::hasColumn('therapist_profiles', 'country_of_operation')) {
                $table->string('country_of_operation', 2)->default('NG')->after('languages');
            }
            if (! Schema::hasColumn('therapist_profiles', 'timezone')) {
                $table->string('timezone')->default('Africa/Lagos')->after('country_of_operation');
            }
            if (! Schema::hasColumn('therapist_profiles', 'payout_currency')) {
                $table->enum('payout_currency', ['NGN', 'USD'])->default('NGN')->after('timezone');
            }
            if (! Schema::hasColumn('therapist_profiles', 'cultural_competencies')) {
                $table->json('cultural_competencies')->nullable()->after('payout_currency');
            }
            if (! Schema::hasColumn('therapist_profiles', 'licensing_country')) {
                $table->string('licensing_country')->nullable()->after('cultural_competencies');
            }
            if (! Schema::hasColumn('therapist_profiles', 'available_for_international')) {
                $table->boolean('available_for_international')->default(true)->after('licensing_country');
            }
            if (! Schema::hasColumn('therapist_profiles', 'available_for_nigeria')) {
                $table->boolean('available_for_nigeria')->default(true)->after('available_for_international');
            }
            // ─── Stripe Connect ────────────────────────────────────────────────
            if (! Schema::hasColumn('therapist_profiles', 'stripe_connect_account_id')) {
                $table->string('stripe_connect_account_id')->nullable()->after('available_for_nigeria');
            }
            if (! Schema::hasColumn('therapist_profiles', 'stripe_connected')) {
                $table->boolean('stripe_connected')->default(false)->after('stripe_connect_account_id');
            }
            // ─── Founding Stipend fields ───────────────────────────────────────
            if (! Schema::hasColumn('therapist_profiles', 'stipend_eligible')) {
                $table->boolean('stipend_eligible')->default(true)->after('stripe_connected');
            }
            if (! Schema::hasColumn('therapist_profiles', 'stipend_months_paid')) {
                $table->unsignedTinyInteger('stipend_months_paid')->default(0)->after('stipend_eligible');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('therapist_profiles')) {
            return;
        }

        Schema::table('therapist_profiles', function (Blueprint $table) {
            $cols = [
                'country_of_operation', 'timezone', 'payout_currency',
                'cultural_competencies', 'licensing_country',
                'available_for_international', 'available_for_nigeria',
                'stripe_connect_account_id', 'stripe_connected',
                'stipend_eligible', 'stipend_months_paid',
            ];
            $existing = array_filter($cols, fn ($c) => Schema::hasColumn('therapist_profiles', $c));
            if ($existing) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};
