<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\Dashboard\GetTherapistDashboardRequest;
use App\Http\Resources\Dashboard\TherapistDashboardResource;
use App\Models\Therapist;
use App\Repositories\Contracts\TherapyRepositoryInterface;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * TherapistDashboardController
 *
 * Handles therapist dashboard endpoints
 * GET    /api/v1/dashboard/therapist/{therapistId}    - Get therapist dashboard
 * GET    /api/v1/dashboard/therapist/earnings          - Get earnings summary
 * GET    /api/v1/dashboard/therapist/patients          - Get patient list
 * GET    /api/v1/dashboard/therapist/top-rated         - Get top therapists
 */
class TherapistDashboardController extends BaseController
{
    public function __construct(
        private DashboardService $dashboardService,
        private TherapyRepositoryInterface $therapyRepository
    ) {}

    /**
     * Get therapist performance dashboard
     */
    public function getDashboard(GetTherapistDashboardRequest $request, int $therapistId): JsonResponse
    {
        try {
            $therapist = Therapist::findOrFail($therapistId);

            Log::info('Retrieving therapist dashboard', ['therapist_id' => $therapistId]);

            $dashboardData = $this->dashboardService->getTherapistDashboard($therapist);

            return $this->success(
                new TherapistDashboardResource($dashboardData),
                'Therapist dashboard retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve therapist dashboard', [
                'therapist_id' => $therapistId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to retrieve dashboard', 500);
        }
    }

    /**
     * Get earnings summary
     */
    public function getEarnings(int $therapistId): JsonResponse
    {
        try {
            $therapist = Therapist::findOrFail($therapistId);

            Log::info('Retrieving therapist earnings', ['therapist_id' => $therapistId]);

            // Assuming user_id of therapist is linked to Therapist model correctly or passed ID is correct
            // Note: TherapyRepository expects user_id for therapist_id in session table usually, check model.
            // In TherapistDashboardController, $therapistId might be Therapist model ID, but sessions use user_id as therapist_id?
            // Let's check: SessionController uses auth()->id() which is user_id.
            // Therapist model has user_id.
            // So we should pass $therapist->user_id to repository.

            $earningsData = $this->therapyRepository->getTherapistEarnings($therapist->user_id);

            return $this->success(
                $earningsData,
                'Earnings summary retrieved'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve earnings', [
                'therapist_id' => $therapistId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to retrieve earnings', 500);
        }
    }

    /**
     * Get patients managed by therapist
     */
    public function getPatients(int $therapistId): JsonResponse
    {
        try {
            $therapist = Therapist::findOrFail($therapistId);

            Log::info('Retrieving therapist patients', ['therapist_id' => $therapistId]);

            $patients = $this->therapyRepository->getTherapistPatients($therapist->user_id);

            return $this->success(
                [
                    'total_patients' => count($patients),
                    'patients' => $patients,
                ],
                'Patients retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve patients', [
                'therapist_id' => $therapistId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to retrieve patients', 500);
        }
    }

    /**
     * Get top-rated therapists
     */
    public function getTopRated(): JsonResponse
    {
        try {
            Log::info('Retrieving top-rated therapists');

            $topTherapists = $this->dashboardService->getTopTherapists(10);

            return $this->success(
                [
                    'total' => count($topTherapists),
                    'therapists' => $topTherapists,
                ],
                'Top-rated therapists retrieved'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve top-rated therapists', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to retrieve therapists', 500);
        }
    }
}
