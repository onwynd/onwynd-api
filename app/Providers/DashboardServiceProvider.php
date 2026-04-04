<?php

namespace App\Providers;

use App\Repositories\Dashboard\AdminDashboardRepository;
use App\Repositories\Dashboard\InstitutionalDashboardRepository;
use App\Repositories\Dashboard\PatientDashboardRepository;
use App\Repositories\Dashboard\TherapistDashboardRepository;
use App\Services\Dashboard\DashboardService;
use Illuminate\Support\ServiceProvider;

class DashboardServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register repositories with singleton pattern for caching consistency
        $this->app->singleton(
            PatientDashboardRepository::class,
            fn ($app) => new PatientDashboardRepository(
                $app->make(\App\Models\Dashboard\PatientDashboard::class)
            )
        );

        $this->app->singleton(
            TherapistDashboardRepository::class,
            fn ($app) => new TherapistDashboardRepository(
                $app->make(\App\Models\Dashboard\TherapistDashboard::class)
            )
        );

        $this->app->singleton(
            InstitutionalDashboardRepository::class,
            fn ($app) => new InstitutionalDashboardRepository(
                $app->make(\App\Models\Dashboard\InstitutionalDashboard::class)
            )
        );

        $this->app->singleton(
            AdminDashboardRepository::class,
            fn ($app) => new AdminDashboardRepository(
                $app->make(\App\Models\Dashboard\AdminDashboard::class)
            )
        );

        // Register main dashboard service
        $this->app->singleton(
            DashboardService::class,
            function ($app) {
                return new DashboardService(
                    $app->make(TherapistDashboardRepository::class),
                    $app->make(PatientDashboardRepository::class),
                    $app->make(InstitutionalDashboardRepository::class),
                    $app->make(AdminDashboardRepository::class)
                );
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observer for real-time dashboard updates
        try {
            \App\Models\TherapySession::observe(\App\Observers\TherapySessionObserver::class);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('TherapySessionObserver could not be registered', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
