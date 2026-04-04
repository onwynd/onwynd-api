<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_dashboards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('total_users')->default(0);
            $table->unsignedBigInteger('total_therapists')->default(0);
            $table->unsignedInteger('new_users_today')->default(0);
            $table->unsignedInteger('active_users_today')->default(0);
            $table->unsignedInteger('active_users_this_month')->default(0);
            $table->unsignedInteger('paying_users')->default(0);
            $table->decimal('d2c_revenue_total', 15, 2)->default(0);
            $table->decimal('d2c_revenue_this_month', 15, 2)->default(0);
            $table->decimal('b2b_revenue_total', 15, 2)->default(0);
            $table->decimal('b2b_revenue_this_month', 15, 2)->default(0);
            $table->decimal('marketplace_revenue_total', 15, 2)->default(0);
            $table->decimal('marketplace_revenue_this_month', 15, 2)->default(0);
            $table->decimal('total_platform_revenue', 15, 2)->default(0);
            $table->unsignedBigInteger('total_sessions_completed')->default(0);
            $table->unsignedInteger('sessions_this_month')->default(0);
            $table->decimal('average_session_rating', 3, 2)->default(0);
            $table->unsignedInteger('total_institutions')->default(0);
            $table->unsignedInteger('active_institutions')->default(0);
            $table->unsignedInteger('physical_centers')->default(0);
            $table->unsignedBigInteger('total_customers_from_institutions')->default(0);
            $table->json('platform_metrics')->nullable();
            $table->json('top_therapists')->nullable();
            $table->json('top_institutions')->nullable();
            $table->decimal('system_health_score', 5, 2)->default(0);
            $table->json('alerts_critical')->nullable();
            $table->timestamps();

            // Only one admin dashboard record typically exists
            $table->unique(['id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_dashboards');
    }
};
