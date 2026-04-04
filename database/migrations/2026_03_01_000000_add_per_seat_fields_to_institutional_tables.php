<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add per-seat pricing fields to subscription_plans
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_plans', 'price_per_seat_ngn')) {
                $table->decimal('price_per_seat_ngn', 12, 2)->nullable()->after('price_ngn')
                    ->comment('NGN price per seat for B2B plans');
            }
            if (! Schema::hasColumn('subscription_plans', 'price_per_seat_usd')) {
                $table->decimal('price_per_seat_usd', 10, 2)->nullable()->after('price_usd')
                    ->comment('USD price per seat for B2B plans');
            }
            if (! Schema::hasColumn('subscription_plans', 'min_seats')) {
                $table->unsignedInteger('min_seats')->default(1)->after('price_per_seat_usd');
            }
            if (! Schema::hasColumn('subscription_plans', 'max_seats')) {
                $table->unsignedInteger('max_seats')->nullable()->after('min_seats')
                    ->comment('NULL = unlimited');
            }
        });

        // Add subscription + paywall fields to organizations
        if (Schema::hasTable('organizations')) {
            Schema::table('organizations', function (Blueprint $table) {
                if (! Schema::hasColumn('organizations', 'contracted_seats')) {
                    $table->unsignedInteger('contracted_seats')->default(0)->after('id');
                }
                if (! Schema::hasColumn('organizations', 'current_seats')) {
                    $table->unsignedInteger('current_seats')->default(0)->after('contracted_seats');
                }
                if (! Schema::hasColumn('organizations', 'subscription_expires_at')) {
                    $table->timestamp('subscription_expires_at')->nullable()->after('current_seats');
                }
                if (! Schema::hasColumn('organizations', 'grace_period_days')) {
                    $table->unsignedSmallInteger('grace_period_days')->default(14)->after('subscription_expires_at');
                }
                if (! Schema::hasColumn('organizations', 'paywall_active')) {
                    $table->boolean('paywall_active')->default(false)->after('grace_period_days');
                }
                if (! Schema::hasColumn('organizations', 'org_type')) {
                    $table->string('org_type', 30)->default('corporate')->after('paywall_active')
                        ->comment('corporate | university | faith_ngo');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_plans', 'price_per_seat_ngn')) {
                $table->dropColumn('price_per_seat_ngn');
            }
            if (Schema::hasColumn('subscription_plans', 'price_per_seat_usd')) {
                $table->dropColumn('price_per_seat_usd');
            }
            if (Schema::hasColumn('subscription_plans', 'min_seats')) {
                $table->dropColumn('min_seats');
            }
            if (Schema::hasColumn('subscription_plans', 'max_seats')) {
                $table->dropColumn('max_seats');
            }
        });

        if (Schema::hasTable('organizations')) {
            Schema::table('organizations', function (Blueprint $table) {
                if (Schema::hasColumn('organizations', 'contracted_seats')) {
                    $table->dropColumn('contracted_seats');
                }
                if (Schema::hasColumn('organizations', 'current_seats')) {
                    $table->dropColumn('current_seats');
                }
                if (Schema::hasColumn('organizations', 'subscription_expires_at')) {
                    $table->dropColumn('subscription_expires_at');
                }
                if (Schema::hasColumn('organizations', 'grace_period_days')) {
                    $table->dropColumn('grace_period_days');
                }
                if (Schema::hasColumn('organizations', 'paywall_active')) {
                    $table->dropColumn('paywall_active');
                }
                if (Schema::hasColumn('organizations', 'org_type')) {
                    $table->dropColumn('org_type');
                }
            });
        }
    }
};
