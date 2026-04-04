<?php

namespace App\Http\Controllers\API\V1\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\StartAssessmentRequest;
use App\Http\Requests\Assessment\SubmitAssessmentRequest;
use App\Models\Assessment;
use App\Models\Assessment\AssessmentResponse;
use App\Models\AssessmentTemplate;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssessmentController extends Controller
{
    public function __construct() {}

    /**
     * Get available assessment templates
     *
     * GET /api/v1/assessments/templates
     */
    public function getAssessmentTemplates(Request $request): JsonResponse
    {
        try {
            $category = $request->get('category');
            $perPage = $request->get('per_page', 20);

            Log::info('Assessment: Get templates', [
                'user_id' => Auth::id(),
                'category' => $category,
            ]);

            $query = AssessmentTemplate::where('is_active', true);

            if ($category) {
                $query->where('category', $category);
            }

            $templates = $query->orderBy('name')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Assessment templates retrieved',
                'data' => [
                    'templates' => $templates->map(fn ($template) => [
                        'id' => $template->id,
                        'name' => $template->name,
                        'description' => $template->description,
                        'category' => $template->category,
                        'duration_minutes' => $template->duration_minutes,
                        'question_count' => $template->questions()->count(),
                        'total_score' => $template->total_score,
                        'image_url' => $template->image_url,
                        'created_at' => $template->created_at,
                    ]),
                    'pagination' => [
                        'total' => $templates->total(),
                        'count' => $templates->count(),
                        'per_page' => $templates->perPage(),
                        'current_page' => $templates->currentPage(),
                        'last_page' => $templates->lastPage(),
                    ],
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Assessment: Get templates failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve templates',
            ], 400);
        }
    }

    /**
     * Get assessment template questions
     *
     * GET /api/v1/assessments/templates/{template}/questions
     */
    public function getTemplateQuestions(AssessmentTemplate $template): JsonResponse
    {
        try {
            Log::info('Assessment: Get template questions', [
                'template_id' => $template->id,
                'user_id' => Auth::id(),
            ]);

            $questions = $template->questions()
                ->orderBy('order')
                ->get()
                ->map(fn ($question) => [
                    'id' => $question->id,
                    'question' => $question->question,
                    'question_type' => $question->question_type, // text, scale, multiple_choice
                    'order' => $question->order,
                    'is_required' => $question->is_required,
                    'options' => $question->question_type === 'multiple_choice'
                        ? json_decode($question->options)
                        : null,
                    'scale_min' => $question->question_type === 'scale' ? $question->scale_min : null,
                    'scale_max' => $question->question_type === 'scale' ? $question->scale_max : null,
                    'scale_min_label' => $question->question_type === 'scale' ? $question->scale_min_label : null,
                    'scale_max_label' => $question->question_type === 'scale' ? $question->scale_max_label : null,
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Template questions retrieved',
                'data' => [
                    'template' => [
                        'id' => $template->id,
                        'name' => $template->name,
                        'description' => $template->description,
                        'category' => $template->category,
                        'duration_minutes' => $template->duration_minutes,
                        'total_score' => $template->total_score,
                    ],
                    'questions' => $questions,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Assessment: Get template questions failed', [
                'template_id' => $template->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve questions',
            ], 400);
        }
    }

    /**
     * Start a new assessment
     *
     * POST /api/v1/assessments/start
     */
    public function startAssessment(StartAssessmentRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            try {
                /** @var User $user */
                $user = Auth::user();
                $template = AssessmentTemplate::findOrFail($request->template_id);

                Log::info('Assessment: Starting assessment', [
                    'user_id' => $user->id,
                    'template_id' => $template->id,
                    'session_id' => $request->session_id ?? null,
                ]);

                // Check if user already has an active assessment
                $activeAssessment = Assessment::where('user_id', $user->id)
                    ->where('status', 'in_progress')
                    ->where('template_id', $template->id)
                    ->first();

                if ($activeAssessment) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Assessment already in progress',
                        'data' => [
                            'assessment_id' => $activeAssessment->id,
                            'template_id' => $template->id,
                        ],
                    ]);
                }

                // Create assessment
                $assessment = Assessment::create([
                    'user_id' => $user->id,
                    'template_id' => $template->id,
                    'session_id' => $request->session_id ?? null,
                    'status' => 'in_progress',
                    'started_at' => now(),
                    'completed_at' => null,
                    'total_score' => null,
                    'score_percentage' => null,
                ]);

                Log::info('Assessment: Started', ['assessment_id' => $assessment->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Assessment started',
                    'data' => [
                        'assessment_id' => $assessment->id,
                        'template_id' => $template->id,
                        'template_name' => $template->name,
                        'question_count' => $template->questions()->count(),
                        'started_at' => $assessment->started_at,
                    ],
                ], 201);

            } catch (Exception $e) {
                Log::error('Assessment: Start failed', [
                    'user_id' => Auth::id(),
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to start assessment: '.$e->getMessage(),
                ], 400);
            }
        });
    }

    /**
     * Submit assessment responses
     *
     * POST /api/v1/assessments/{assessment}/submit
     */
    public function submitAssessment(Assessment $assessment, SubmitAssessmentRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($assessment, $request) {
            try {
                /** @var User $user */
                $user = Auth::user();

                if ($assessment->user_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized',
                    ], 403);
                }

                if ($assessment->status !== 'in_progress') {
                    throw new Exception('Assessment not in progress');
                }

                Log::info('Assessment: Submitting', [
                    'assessment_id' => $assessment->id,
                    'response_count' => count($request->responses),
                ]);

                // Store responses
                foreach ($request->responses as $response) {
                    AssessmentResponse::create([
                        'assessment_id' => $assessment->id,
                        'question_id' => $response['question_id'],
                        'response_value' => $response['response_value'],
                        'response_type' => $response['response_type'],
                        'answered_at' => now(),
                    ]);
                }

                // Calculate score
                $score = $this->calculateScore($assessment);

                // Complete assessment
                $assessment->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'total_score' => $score['total'],
                    'score_percentage' => $score['percentage'],
                ]);

                Log::info('Assessment: Submitted', [
                    'assessment_id' => $assessment->id,
                    'score' => $score['total'],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Assessment submitted',
                    'data' => [
                        'assessment_id' => $assessment->id,
                        'status' => $assessment->status,
                        'total_score' => $score['total'],
                        'score_percentage' => $score['percentage'],
                        'interpretation' => $this->getScoreInterpretation($assessment->template, $score['percentage']),
                        'completed_at' => $assessment->completed_at,
                    ],
                ]);

            } catch (Exception $e) {
                Log::error('Assessment: Submit failed', [
                    'assessment_id' => $assessment->id ?? null,
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to submit assessment: '.$e->getMessage(),
                ], 400);
            }
        });
    }

    /**
     * Get user's assessments
     *
     * GET /api/v1/assessments
     */
    public function getAssessments(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $status = $request->get('status');
            $templateId = $request->get('template_id');
            $perPage = $request->get('per_page', 20);

            Log::info('Assessment: Get user assessments', [
                'user_id' => $user->id,
                'status' => $status,
            ]);

            $query = $user->assessments()
                ->with('template')
                ->orderByDesc('created_at');

            if ($status) {
                $query->where('status', $status);
            }

            if ($templateId) {
                $query->where('template_id', $templateId);
            }

            $assessments = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Assessments retrieved',
                'data' => [
                    'assessments' => $assessments->map(fn ($assessment) => [
                        'id' => $assessment->id,
                        'template_id' => $assessment->template_id,
                        'template_name' => $assessment->template->name,
                        'category' => $assessment->template->category,
                        'status' => $assessment->status,
                        'total_score' => $assessment->total_score,
                        'score_percentage' => $assessment->score_percentage,
                        'interpretation' => $assessment->status === 'completed'
                            ? $this->getScoreInterpretation($assessment->template, $assessment->score_percentage)
                            : null,
                        'started_at' => $assessment->started_at,
                        'completed_at' => $assessment->completed_at,
                        'session_id' => $assessment->session_id,
                    ]),
                    'pagination' => [
                        'total' => $assessments->total(),
                        'count' => $assessments->count(),
                        'per_page' => $assessments->perPage(),
                        'current_page' => $assessments->currentPage(),
                        'last_page' => $assessments->lastPage(),
                    ],
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Assessment: Get assessments failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assessments',
            ], 400);
        }
    }

    /**
     * Get current/in-progress assessment
     *
     * GET /api/v1/assessments/current
     */
    public function getCurrentAssessment(): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            $assessment = $user->assessments()
                ->where('status', 'in_progress')
                ->with('template', 'responses')
                ->first();

            if (! $assessment) {
                return response()->json([
                    'success' => false,
                    'message' => 'No assessment in progress',
                ], 404);
            }

            Log::info('Assessment: Get current', [
                'user_id' => $user->id,
                'assessment_id' => $assessment->id,
            ]);

            $template = $assessment->template;
            $questions = $template->questions()
                ->orderBy('order')
                ->get();

            $responses = $assessment->responses()
                ->pluck('response_value', 'question_id')
                ->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Current assessment',
                'data' => [
                    'assessment_id' => $assessment->id,
                    'template' => [
                        'id' => $template->id,
                        'name' => $template->name,
                        'category' => $template->category,
                    ],
                    'progress' => [
                        'answered' => count($responses),
                        'total' => $questions->count(),
                    ],
                    'questions' => $questions->map(fn ($q) => [
                        'id' => $q->id,
                        'question' => $q->question,
                        'question_type' => $q->question_type,
                        'order' => $q->order,
                        'is_required' => $q->is_required,
                        'answered' => isset($responses[$q->id]),
                        'response_value' => $responses[$q->id] ?? null,
                        'options' => $q->question_type === 'multiple_choice'
                            ? json_decode($q->options)
                            : null,
                    ]),
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Assessment: Get current failed', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve current assessment',
            ], 400);
        }
    }

    /**
     * Get assessment details
     *
     * GET /api/v1/assessments/{assessment}
     */
    public function getAssessmentDetails(Assessment $assessment): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            if ($assessment->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            Log::info('Assessment: Get details', ['assessment_id' => $assessment->id]);

            $responses = $assessment->responses()
                ->with('question')
                ->get()
                ->map(fn ($r) => [
                    'question' => $r->question->question,
                    'question_type' => $r->question->question_type,
                    'response' => $r->response_value,
                    'answered_at' => $r->answered_at,
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Assessment details',
                'data' => [
                    'id' => $assessment->id,
                    'template' => [
                        'id' => $assessment->template->id,
                        'name' => $assessment->template->name,
                        'category' => $assessment->template->category,
                        'description' => $assessment->template->description,
                    ],
                    'status' => $assessment->status,
                    'total_score' => $assessment->total_score,
                    'score_percentage' => $assessment->score_percentage,
                    'interpretation' => $assessment->status === 'completed'
                        ? $this->getScoreInterpretation($assessment->template, $assessment->score_percentage)
                        : null,
                    'started_at' => $assessment->started_at,
                    'completed_at' => $assessment->completed_at,
                    'responses' => $responses,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Assessment: Get details failed', [
                'assessment_id' => $assessment->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assessment details',
            ], 400);
        }
    }

    /**
     * Delete assessment (only drafts)
     *
     * DELETE /api/v1/assessments/{assessment}
     */
    public function deleteAssessment(Assessment $assessment): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            if ($assessment->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            if ($assessment->status !== 'in_progress') {
                throw new Exception('Can only delete in-progress assessments');
            }

            Log::info('Assessment: Deleting', ['assessment_id' => $assessment->id]);

            $assessment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Assessment deleted',
            ]);

        } catch (Exception $e) {
            Log::error('Assessment: Delete failed', [
                'assessment_id' => $assessment->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete assessment: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Calculate assessment score based on responses
     *
     * @return array Score data
     */
    private function calculateScore(Assessment $assessment): array
    {
        try {
            $template = $assessment->template;
            $templateName = strtolower($template->name ?? '');

            // Default/fallback
            $totalPossibleScore = $template->total_score ?? 100;

            // Known instruments max scores
            if (str_contains($templateName, 'phq-9') || str_contains($templateName, 'phq9')) {
                $totalPossibleScore = 27;
            } elseif (str_contains($templateName, 'gad-7') || str_contains($templateName, 'gad7')) {
                $totalPossibleScore = 21;
            } elseif (str_contains($templateName, 'pss-10') || str_contains($templateName, 'perceived stress')) {
                $totalPossibleScore = 40;
            } elseif (str_contains($templateName, 'who-5') || str_contains($templateName, 'well-being')) {
                $totalPossibleScore = 25;
            }

            $score = 0;
            $responses = $assessment->responses()->with('question')->get();

            foreach ($responses as $response) {
                $question = $response->question;

                // Score based on question type
                if ($question->question_type === 'scale') {
                    $value = intval($response->response_value);

                    // PSS-10 reverse items 4,5,7,8 if order meta is available
                    if (str_contains($templateName, 'pss-10') || str_contains($templateName, 'perceived stress')) {
                        $order = $question->order ?? $question->order_index ?? $question->order_number ?? null;
                        if ($order !== null && in_array(intval($order), [4, 5, 7, 8], true)) {
                            $value = 4 - max(0, min(4, $value));
                        }
                    }

                    $score += $value;
                } elseif ($question->question_type === 'multiple_choice') {
                    // Multiple choice: check if correct
                    $correctAnswer = $question->correct_answer ?? null;
                    if ($response->response_value === $correctAnswer) {
                        $score += 1;
                    }
                }
            }

            $percentage = ($score / $totalPossibleScore) * 100;

            // WHO-5 conventional transformed score (0-100)
            if (str_contains($templateName, 'who-5') || str_contains($templateName, 'well-being')) {
                $percentage = ($score / 25) * 100;
            }

            return [
                'total' => $score,
                'percentage' => round($percentage, 2),
                'max' => $totalPossibleScore,
            ];

        } catch (Exception $e) {
            Log::error('Score calculation failed', [
                'assessment_id' => $assessment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'total' => 0,
                'percentage' => 0,
                'max' => 100,
            ];
        }
    }

    /**
     * Get interpretation of score
     *
     * @return string Interpretation
     */
    private function getScoreInterpretation(AssessmentTemplate $template, float $percentage): string
    {
        return match (true) {
            $percentage >= 90 => 'Excellent',
            $percentage >= 75 => 'Good',
            $percentage >= 60 => 'Moderate',
            $percentage >= 40 => 'Fair',
            default => 'Low'
        };
    }
}
