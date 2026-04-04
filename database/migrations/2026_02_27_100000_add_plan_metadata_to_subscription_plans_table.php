<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // UUID for public API exposure (so we don't expose numeric IDs)
            if (! Schema::hasColumn('subscription_plans', 'uuid')) {
                $table->uuid('uuid')->nullable()->after('id');
            }

            // Plan targeting — what audience/channel this plan is for
            if (! Schema::hasColumn('subscription_plans', 'plan_type')) {
                $table->enum('plan_type', ['d2c', 'b2b_corporate', 'b2b_university', 'b2b_faith_ngo'])
                    ->default('d2c')
                    ->after('slug');
            }

            // Marketing / display flags
            if (! Schema::hasColumn('subscription_plans', 'is_popular')) {
                $table->boolean('is_popular')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('subscription_plans', 'is_recommended')) {
                $table->boolean('is_recommended')->default(false)->after('is_popular');
            }
            if (! Schema::hasColumn('subscription_plans', 'best_for')) {
                $table->string('best_for', 300)->nullable()->after('is_recommended');
            }
            if (! Schema::hasColumn('subscription_plans', 'conversion_target')) {
                $table->unsignedSmallInteger('conversion_target')->nullable()->after('best_for');
            }
            if (! Schema::hasColumn('subscription_plans', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('conversion_target');
            }
        });

        // Back-fill UUIDs for existing rows that don't have UUIDs
        if (Schema::hasColumn('subscription_plans', 'uuid')) {
            DB::table('subscription_plans')->whereNull('uuid')->orderBy('id')->each(function ($row) {
                DB::table('subscription_plans')
                    ->where('id', $row->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });

            // Now make uuid non-nullable
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->uuid('uuid')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([
                'uuid', 'plan_type', 'is_popular',
                'is_recommended', 'best_for', 'conversion_target', 'sort_order',
            ]);
        });
    }
};
