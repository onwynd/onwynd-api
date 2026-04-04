<?php

/**
 * Dashboard API Routes
 *
 * Role-based dashboard endpoints for Onwynd platform
 * Supports four main dashboards: Patient, Therapist, Institutional, and Admin
 *
 * NOTE: This file is included from routes/api.php within the 'v1' prefix
 * So routes should be prefixed with 'dashboard' only
 */

use App\Http\Controllers\API\V1\Dashboard\AdminDashboardController;
use App\Http\Controllers\API\V1\Dashboard\InstitutionalDashboardController;
use App\Http\Controllers\API\V1\Dashboard\PatientDashboardController;
use App\Http\Controllers\API\V1\Dashboard\TherapistDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')->middleware('auth:sanctum')->group(function () {

    // Patient Dashboard Routes
    Route::prefix('patient')->group(function () {
        Route::get('/{userId}', [PatientDashboardController::class, 'getDashboard'])
            ->name('patient.dashboard.get');
        Route::post('/{userId}/mood', [PatientDashboardController::class, 'recordMood'])
            ->name('patient.mood.record');
        Route::get('/{userId}/support', [PatientDashboardController::class, 'getSupportRecommendations'])
            ->name('patient.support.recommendations');
    });

    // Therapist Dashboard Routes
    Route::prefix('therapist')->group(function () {
        Route::get('/{therapistId}', [TherapistDashboardController::class, 'getDashboard'])
            ->name('therapist.dashboard.get');
        Route::get('/{therapistId}/earnings', [TherapistDashboardController::class, 'getEarnings'])
            ->name('therapist.earnings.get');
        Route::get('/{therapistId}/patients', [TherapistDashboardController::class, 'getPatients'])
            ->name('therapist.patients.get');
        Route::get('/top-rated', [TherapistDashboardController::class, 'getTopRated'])
            ->name('therapist.top-rated');
    });

    // Institutional Dashboard Routes
    Route::prefix('institution')->group(function () {
        Route::get('/{institutionId}', [InstitutionalDashboardController::class, 'getDashboard'])
            ->name('institutional.dashboard.get');
        Route::get('/{institutionId}/roi', [InstitutionalDashboardController::class, 'getROI'])
            ->name('institutional.roi.get');
        Route::get('/{institutionId}/health', [InstitutionalDashboardController::class, 'getHealthAssessment'])
            ->name('institutional.health.get');
        Route::get('/active', [InstitutionalDashboardController::class, 'getActivePartners'])
            ->name('institutional.active.list');
        Route::get('/renewal-due', [InstitutionalDashboardController::class, 'getRenewalDue'])
            ->name('institutional.renewal-due.list');
    });

    // Product Manager Dashboard Routes
    Route::prefix('product')->middleware('role:product-manager')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\V1\ProductManager\DashboardController::class, 'getDashboard'])
            ->name('pm.dashboard.get');

        // Settings Access for PM
        Route::get('/settings/features', [\App\Http\Controllers\API\V1\Admin\SettingsController::class, 'getFeatureToggles']);
        Route::put('/settings/features', [\App\Http\Controllers\API\V1\Admin\SettingsController::class, 'updateGroup']); // Specifically for features

        Route::get('/settings/plans', [\App\Http\Controllers\API\V1\Admin\SubscriptionPlanController::class, 'index']);
        Route::post('/settings/plans', [\App\Http\Controllers\API\V1\Admin\SubscriptionPlanController::class, 'store']);
        Route::put('/settings/plans/{plan}', [\App\Http\Controllers\API\V1\Admin\SubscriptionPlanController::class, 'update']);
        Route::delete('/settings/plans/{plan}', [\App\Http\Controllers\API\V1\Admin\SubscriptionPlanController::class, 'destroy']);
    });

    // Admin Dashboard Routes (Protected)
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'getDashboard'])
            ->name('admin.dashboard.get');
        Route::post('/refresh', [AdminDashboardController::class, 'refreshMetrics'])
            ->name('admin.metrics.refresh');
        Route::get('/alerts', [AdminDashboardController::class, 'getCriticalAlerts'])
            ->name('admin.alerts.critical');
        Route::get('/support', [AdminDashboardController::class, 'getUsersNeedingSupport'])
            ->name('admin.users.support');
        Route::get('/institutions', [AdminDashboardController::class, 'getInstitutionMetrics'])
            ->name('admin.institutions.metrics');
        Route::get('/revenue', [AdminDashboardController::class, 'getRevenueAnalytics'])
            ->name('admin.revenue.analytics');

        // Settings Routes
        Route::get('/settings', [\App\Http\Controllers\API\V1\Admin\SettingsController::class, 'index']);
        Route::put('/settings', [\App\Http\Controllers\API\V1\Admin\SettingsController::class, 'update']);
        Route::put('/settings/{group}', [\App\Http\Controllers\API\V1\Admin\SettingsController::class, 'updateGroup']);
        Route::get('/settings/ai', [\App\Http\Controllers\API\V1\Admin\SettingsController::class, 'getAISettings']);
        Route::get('/settings/features', [\App\Http\Controllers\API\V1\Admin\SettingsController::class, 'getFeatureToggles']);
    });

});
