<?php

namespace App\Http\Controllers\API\V1\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitGuestAssessmentRequest;
use App\Models\Assessment;
use App\Models\GuestAssessmentResult;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GuestAssessmentController extends Controller
{
    private const PENDING_ASSESSMENT_COOKIE = 'onwynd_pending_assessment';
    private const PENDING_ASSESSMENT_TTL_MINUTES = 15;

    /**
     * POST /api/v1/assessments/guest/submit
     *
     * Submit assessment results for unauthenticated users
     * Stores results temporarily and returns a guest token for later account linking
     */
    public function submit(SubmitGuestAssessmentRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            try {
                $assessment = Assessment::where('uuid', $request->assessment_uuid)->firstOrFail();

                // Generate unique guest token for this assessment
                $guestToken = Str::uuid()->toString();

                // Calculate scores and severity (same logic as authenticated assessments)
                $answers = $request->answers;
                $rawScore = collect($answers)->sum('score');
                $totalPossibleScore = $assessment->questions->count() * 3; // Assuming 0-3 scale
                $percentage = $totalPossibleScore > 0 ? round(($rawScore / $totalPossibleScore) * 100) : 0;

                // Determine severity level based on assessment type
                $severityLabel = $this->determineSeverity($assessment->type, $rawScore);

                // Generate AI interpretation (same as authenticated flow)
                $interpretation = $this->generateInterpretation($assessment, $rawScore, $severityLabel);

                // Create guest assessment result
                $guestResult = GuestAssessmentResult::create([
                    'assessment_id' => $assessment->id,
                    'guest_token' => $guestToken,
                    'answers' => $answers,
                    'total_score' => $rawScore,
                    'percentage' => $percentage,
                    'severity_level' => $severityLabel,
                    'interpretation' => $interpretation,
                    'recommendations' => $this->generateRecommendations($severityLabel),
                    'completed_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Assessment submitted successfully',
                    'data' => [
                        'result' => $guestResult,
                        'guest_token' => $guestToken,
                    ],
                ])->cookie(
                    self::PENDING_ASSESSMENT_COOKIE,
                    $guestToken,
                    self::PENDING_ASSESSMENT_TTL_MINUTES,
                    '/',
                    null,
                    app()->environment('production'),
                    true,
                    false,
                    'Lax'
                );

            } catch (Exception $e) {
                Log::error('GuestAssessment: submit failed', [
                    'assessment_uuid' => $request->assessment_uuid,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to submit assessment',
                ], 500);
            }
        });
    }

    /**
     * POST /api/v1/assessments/guest/link
     *
     * Link guest assessment results to a user account after registration
     */
    public function link(Request $request): JsonResponse
    {
        $request->validate([
            'guest_token' => 'required|string',
        ]);

        return DB::transaction(function () use ($request) {
            try {
                $user = $request->user();
                $guestToken = $request->guest_token;

                // Find guest assessment result
                $guestResult = GuestAssessmentResult::where('guest_token', $guestToken)
                    ->whereNull('linked_user_id')
                    ->first();

                if (! $guestResult) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid or already linked guest token',
                    ], 404);
                }

                // Create user assessment result from guest result
                $userResult = $guestResult->linkToUser($user);

                return response()->json([
                    'success' => true,
                    'message' => 'Assessment linked to account successfully',
                    'data' => $userResult,
                ]);

            } catch (Exception $e) {
                Log::error('GuestAssessment: link failed', [
                    'user_id' => $request->user()->id,
                    'guest_token' => $request->guest_token,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to link assessment',
                ], 500);
            }
        });
    }

    /**
     * GET /api/v1/patient/assessments/guest/pending
     *
     * Check whether there is a recent, unlinked guest assessment in the HttpOnly cookie.
     * Used to prompt the user (after sign-in) whether they'd like to save it to their account.
     */
    public function pending(Request $request): JsonResponse
    {
        $guestToken = $request->cookie(self::PENDING_ASSESSMENT_COOKIE);
        if (! $guestToken) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_pending' => false,
                ],
            ]);
        }

        $guestResult = GuestAssessmentResult::where('guest_token', $guestToken)
            ->whereNull('linked_user_id')
            ->first();

        if (! $guestResult) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_pending' => false,
                ],
            ])->withoutCookie(self::PENDING_ASSESSMENT_COOKIE);
        }

        $completedAt = $guestResult->completed_at ?? $guestResult->created_at;
        if ($completedAt && $completedAt->lt(now()->subMinutes(self::PENDING_ASSESSMENT_TTL_MINUTES))) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_pending' => false,
                    'expired' => true,
                ],
            ])->withoutCookie(self::PENDING_ASSESSMENT_COOKIE);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'has_pending' => true,
                'result' => $guestResult,
            ],
        ]);
    }

    /**
     * GET /api/v1/assessments/guest/pending
     *
     * Public version of pending() for unauthenticated users so results can be restored
     * after refresh within the TTL window. Reads from the HttpOnly cookie.
     */
    public function pendingPublic(Request $request): JsonResponse
    {
        $guestToken = $request->cookie(self::PENDING_ASSESSMENT_COOKIE);
        if (! $guestToken) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_pending' => false,
                ],
            ]);
        }

        $guestResult = GuestAssessmentResult::where('guest_token', $guestToken)
            ->whereNull('linked_user_id')
            ->first();

        if (! $guestResult) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_pending' => false,
                ],
            ])->withoutCookie(self::PENDING_ASSESSMENT_COOKIE);
        }

        $completedAt = $guestResult->completed_at ?? $guestResult->created_at;
        if ($completedAt && $completedAt->lt(now()->subMinutes(self::PENDING_ASSESSMENT_TTL_MINUTES))) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_pending' => false,
                    'expired' => true,
                ],
            ])->withoutCookie(self::PENDING_ASSESSMENT_COOKIE);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'has_pending' => true,
                'result' => $guestResult,
            ],
        ]);
    }

    /**
     * POST /api/v1/patient/assessments/guest/attach
     *
     * Attach the pending guest assessment (from HttpOnly cookie) to the authenticated user.
     * Clears the cookie on success.
     */
    public function attach(Request $request): JsonResponse
    {
        $user = $request->user();
        $guestToken = $request->cookie(self::PENDING_ASSESSMENT_COOKIE);

        if (! $guestToken) {
            return response()->json([
                'success' => false,
                'message' => 'No pending assessment found',
            ], 404);
        }

        return DB::transaction(function () use ($user, $guestToken) {
            $guestResult = GuestAssessmentResult::where('guest_token', $guestToken)
                ->whereNull('linked_user_id')
                ->first();

            if (! $guestResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or already linked pending assessment',
                ], 404)->withoutCookie(self::PENDING_ASSESSMENT_COOKIE);
            }

            $completedAt = $guestResult->completed_at ?? $guestResult->created_at;
            if ($completedAt && $completedAt->lt(now()->subMinutes(self::PENDING_ASSESSMENT_TTL_MINUTES))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pending assessment expired',
                ], 410)->withoutCookie(self::PENDING_ASSESSMENT_COOKIE);
            }

            $userResult = $guestResult->linkToUser($user);

            return response()->json([
                'success' => true,
                'message' => 'Assessment saved to your account',
                'data' => $userResult,
            ])->withoutCookie(self::PENDING_ASSESSMENT_COOKIE);
        });
    }

    /**
     * GET /api/v1/assessments/guest/{guest_token}
     *
     * Retrieve guest assessment result by token
     */
    public function show(string $guestToken): JsonResponse
    {
        try {
            $guestResult = GuestAssessmentResult::where('guest_token', $guestToken)
                ->with('assessment')
                ->first();

            if (! $guestResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guest assessment not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Guest assessment retrieved',
                'data' => $guestResult,
            ]);

        } catch (Exception $e) {
            Log::error('GuestAssessment: show failed', [
                'guest_token' => $guestToken,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve guest assessment',
            ], 500);
        }
    }

    /**
     * Determine severity level based on assessment type and score
     */
    private function determineSeverity(string $assessmentType, int $score): string
    {
        return match ($assessmentType) {
            'phq-9' => match (true) {
                $score <= 4 => 'Minimal',
                $score <= 9 => 'Mild',
                $score <= 14 => 'Moderate',
                $score <= 19 => 'Moderately Severe',
                default => 'Severe',
            },
            'gad-7' => match (true) {
                $score <= 4 => 'Minimal',
                $score <= 9 => 'Mild',
                $score <= 14 => 'Moderate',
                default => 'Severe',
            },
            'pss-10' => match (true) {
                $score <= 13 => 'Low Stress',
                $score <= 26 => 'Moderate Stress',
                default => 'High Stress',
            },
            'who-5' => match (true) {
                $score >= 50 => 'Good Well-being',
                $score >= 25 => 'Moderate Well-being',
                default => 'Low Well-being',
            },
            default => 'Moderate',
        };
    }

    /**
     * Generate AI interpretation based on assessment and score
     */
    private function generateInterpretation(Assessment $assessment, int $score, string $severity): string
    {
        $assessmentName = match ($assessment->type) {
            'phq-9' => 'depression',
            'gad-7' => 'anxiety',
            'pss-10' => 'stress',
            'who-5' => 'well-being',
            default => 'mental health',
        };

        return match ($severity) {
            'Minimal', 'Low Stress', 'Good Well-being' => "Based on your responses, you show signs of good {$assessmentName} levels. Keep up the positive habits!",
            'Mild', 'Moderate Stress', 'Moderate Well-being' => "Based on your responses, you show signs of moderate {$assessmentName} levels. This is common and manageable with the right support and strategies.",
            'Moderate' => "Based on your responses, you show signs of moderate {$assessmentName} levels. Consider implementing some self-care strategies.",
            'Moderately Severe', 'High Stress', 'Low Well-being' => "Based on your responses, you show signs of significant {$assessmentName} levels. Consider speaking with a mental health professional.",
            'Severe' => "Based on your responses, you show signs of severe {$assessmentName} levels. We strongly recommend speaking with a mental health professional.",
            default => "Based on your responses, you show signs of {$assessmentName} levels that may benefit from attention and care.",
        };
    }

    /**
     * Generate recommendations based on severity level
     */
    private function generateRecommendations(string $severity): array
    {
        $baseRecommendations = [
            'Practice mindfulness meditation daily',
            'Maintain a regular sleep schedule',
            'Connect with supportive friends and family',
        ];

        $additionalRecommendations = match ($severity) {
            'Minimal', 'Low Stress', 'Good Well-being' => [
                'Continue your current wellness practices',
                'Consider helping others who may be struggling',
            ],
            'Mild', 'Moderate Stress', 'Moderate Well-being' => [
                'Consider journaling your thoughts and feelings',
                'Try relaxation techniques like deep breathing',
            ],
            'Moderate' => [
                'Consider speaking with a mental health professional',
                'Explore stress management techniques',
            ],
            'Moderately Severe', 'High Stress', 'Low Well-being', 'Severe' => [
                'We strongly recommend speaking with a mental health professional',
                'Consider reaching out to a therapist or counselor',
                "Don't hesitate to seek professional support",
            ],
            default => [
                'Consider speaking with a mental health professional',
            ],
        };

        return array_merge($baseRecommendations, $additionalRecommendations);
    }
}
