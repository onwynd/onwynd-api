<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\Dashboard\GetPatientDashboardRequest;
use App\Http\Requests\Dashboard\RecordPatientMoodRequest;
use App\Http\Resources\Dashboard\PatientDashboardResource;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * PatientDashboardController
 *
 * Handles patient dashboard endpoints
 * GET    /api/v1/dashboard/patient/{userId}  - Get patient dashboard
 * POST   /api/v1/dashboard/patient/{userId}/mood - Record mood check-in
 * GET    /api/v1/dashboard/patient/{userId}/support - Get support recommendations
 */
class PatientDashboardController extends BaseController
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    /**
     * Get patient wellness dashboard
     */
    public function getDashboard(GetPatientDashboardRequest $request, int $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);

            Log::info('Retrieving patient dashboard', ['user_id' => $userId]);

            $dashboardData = $this->dashboardService->getPatientDashboard($user);

            return $this->sendResponse(
                new PatientDashboardResource($dashboardData),
                'Patient dashboard retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve patient dashboard', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return $this->sendError('Failed to retrieve dashboard', 500);
        }
    }

    /**
     * Record mood check-in
     */
    public function recordMood(RecordPatientMoodRequest $request, int $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);

            Log::info('Recording mood for patient', [
                'user_id' => $userId,
                'mood_score' => $request->getMoodScore(),
            ]);

            $dashboard = app()->make(
                \App\Repositories\Dashboard\PatientDashboardRepository::class
            )->recordMood(
                $userId,
                $request->getMoodScore(),
                $request->getNote()
            );

            return $this->sendResponse(
                [
                    'mood_recorded' => true,
                    'current_mood' => $dashboard->current_mood,
                    'streak' => $dashboard->current_streak,
                    'trend' => $dashboard->getMoodTrend(),
                ],
                'Mood recorded successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to record mood', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return $this->sendError('Failed to record mood', 500);
        }
    }

    /**
     * Get support recommendations
     */
    public function getSupportRecommendations(int $userId): JsonResponse
    {
        try {
            Log::info('Retrieving support recommendations', ['user_id' => $userId]);

            $user = User::findOrFail($userId);
            $dashboardData = $this->dashboardService->getPatientDashboard($user);

            $recommendations = $dashboardData['wellness']['recommended_actions'] ?? [];
            $needsSupport = $dashboardData['wellness']['needs_support'] ?? false;

            return $this->sendResponse(
                [
                    'needs_support' => $needsSupport,
                    'recommendations' => $recommendations,
                    'mood_trend' => $dashboardData['wellness']['mood_trend'] ?? null,
                    'current_mood' => $dashboardData['wellness']['current_mood'] ?? null,
                    'suggested_actions' => $this->buildActionItems($recommendations),
                ],
                'Support recommendations retrieved'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve support recommendations', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to retrieve recommendations', 500);
        }
    }

    /**
     * Build action items from recommendations
     */
    private function buildActionItems(array $recommendations): array
    {
        $actionMap = [
            'Resume daily check-ins' => [
                'type' => 'engagement',
                'priority' => 'high',
                'action' => 'complete_mood_checkin',
            ],
            'Book a therapy session' => [
                'type' => 'therapy',
                'priority' => 'high',
                'action' => 'book_session',
            ],
            'Try a guided meditation' => [
                'type' => 'self_care',
                'priority' => 'medium',
                'action' => 'start_meditation',
            ],
            'Connect with your therapist' => [
                'type' => 'support',
                'priority' => 'high',
                'action' => 'message_therapist',
            ],
        ];

        return collect($recommendations)
            ->map(fn ($rec) => [
                'recommendation' => $rec,
                ...$actionMap[$rec] ?? ['type' => 'other', 'priority' => 'medium'],
            ])
            ->toArray();
    }
}
