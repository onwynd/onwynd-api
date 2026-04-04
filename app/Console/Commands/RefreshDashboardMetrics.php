<?php

namespace App\Console\Commands;

use App\Repositories\Dashboard\AdminDashboardRepository;
use App\Repositories\Dashboard\InstitutionalDashboardRepository;
use App\Repositories\Dashboard\PatientDashboardRepository;
use App\Repositories\Dashboard\TherapistDashboardRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RefreshDashboardMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashboard:refresh
                            {--type=all : The dashboard type to refresh (all|admin|therapist|patient|institutional)}
                            {--user-id= : Specific user ID to refresh (for patient/therapist)}
                            {--institution-id= : Specific institution ID to refresh (for institutional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh dashboard metrics for all users or specific dashboard types';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->option('type');
        $startTime = microtime(true);

        try {
            match ($type) {
                'all' => $this->refreshAllDashboards(),
                'admin' => $this->refreshAdminDashboard(),
                'therapist' => $this->refreshTherapistDashboards(),
                'patient' => $this->refreshPatientDashboards(),
                'institutional' => $this->refreshInstitutionalDashboards(),
                default => $this->error("Invalid type: {$type}. Use: all|admin|therapist|patient|institutional")
            };

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->info("\n✓ Dashboard refresh completed in {$duration}ms");

            Log::info('Dashboard metrics refresh completed', [
                'type' => $type,
                'duration_ms' => $duration,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error refreshing dashboard metrics: {$e->getMessage()}");
            Log::error('Dashboard metrics refresh failed', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Refresh all dashboard types
     */
    private function refreshAllDashboards(): void
    {
        $this->refreshAdminDashboard();
        $this->refreshInstitutionalDashboards();
        $this->refreshTherapistDashboards();
        $this->refreshPatientDashboards();
    }

    /**
     * Refresh admin dashboard
     */
    private function refreshAdminDashboard(): void
    {
        $this->line('Refreshing admin dashboard...');

        $adminRepo = app(AdminDashboardRepository::class);
        $adminRepo->updateMetrics();
        Cache::forget('admin_dashboard');

        $this->info('✓ Admin dashboard refreshed');
    }

    /**
     * Refresh institutional dashboards
     */
    private function refreshInstitutionalDashboards(): void
    {
        $this->line('Refreshing institutional dashboards...');

        if ($institutionId = $this->option('institution-id')) {
            $institutionalRepo = app(InstitutionalDashboardRepository::class);
            $institutionalRepo->updateMetrics($institutionId);
            Cache::forget("institutional_dashboard_{$institutionId}");
            $this->info("✓ Institutional dashboard #{$institutionId} refreshed");
        } else {
            $institutionalRepo = app(InstitutionalDashboardRepository::class);
            $institutionalRepo->bulkUpdate();
            Cache::forget('institutional_dashboards:*');
            $this->info('✓ All institutional dashboards refreshed');
        }
    }

    /**
     * Refresh therapist dashboards
     */
    private function refreshTherapistDashboards(): void
    {
        $this->line('Refreshing therapist dashboards...');

        if ($userId = $this->option('user-id')) {
            $therapistRepo = app(TherapistDashboardRepository::class);
            $therapist = \App\Models\Therapist::find($userId);
            if ($therapist) {
                $therapistRepo->updateMetrics($therapist);
                Cache::forget("therapist_dashboard_{$userId}");
                $this->info("✓ Therapist dashboard #{$userId} refreshed");
            } else {
                $this->warn("Therapist #{$userId} not found");
            }
        } else {
            $therapistRepo = app(TherapistDashboardRepository::class);
            $therapists = \App\Models\Therapist::all();
            $count = 0;

            foreach ($therapists as $therapist) {
                try {
                    $therapistRepo->updateMetrics($therapist);
                    Cache::forget("therapist_dashboard_{$therapist->user_id}");
                    $count++;
                } catch (\Exception $e) {
                    $this->warn("Failed to refresh therapist #{$therapist->user_id}: {$e->getMessage()}");
                }
            }

            $this->info("✓ Refreshed {$count} therapist dashboards");
        }
    }

    /**
     * Refresh patient dashboards
     */
    private function refreshPatientDashboards(): void
    {
        $this->line('Refreshing patient dashboards...');

        if ($userId = $this->option('user-id')) {
            $patientRepo = app(PatientDashboardRepository::class);
            $patientRepo->updateMetrics($userId);
            Cache::forget("patient_dashboard_{$userId}");
            $this->info("✓ Patient dashboard #{$userId} refreshed");
        } else {
            // Refresh only active patients (to avoid wasting resources on inactive accounts)
            $patientRepo = app(PatientDashboardRepository::class);
            $patients = \App\Models\User::where('role_id',
                \App\Models\Role::where('slug', 'customer')->value('id'))
                ->where('is_active', true)
                ->pluck('id');

            $count = 0;
            foreach ($patients as $userId) {
                try {
                    $patientRepo->updateMetrics($userId);
                    Cache::forget("patient_dashboard_{$userId}");
                    $count++;
                } catch (\Exception $e) {
                    $this->warn("Failed to refresh patient #{$userId}: {$e->getMessage()}");
                }
            }

            $this->info("✓ Refreshed {$count} patient dashboards");
        }
    }
}
