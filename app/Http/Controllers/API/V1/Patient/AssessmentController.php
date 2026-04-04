<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Events\AssessmentCompleted;
use App\Http\Controllers\API\BaseController;
use App\Models\Assessment;
use App\Models\UserAssessmentResult;
use App\Services\AI\AIService;
use App\Services\Therapy\TherapistMatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AssessmentController extends BaseController
{
    protected $aiService;

    protected $matchingService;

    public function __construct(AIService $aiService, TherapistMatchingService $matchingService)
    {
        $this->aiService = $aiService;
        $this->matchingService = $matchingService;
    }

    public function index(Request $request)
    {
        $assessments = Assessment::where('is_active', true)->get();

        return $this->sendResponse($assessments, 'Available assessments retrieved successfully.');
    }

    public function questions(Request $request)
    {
        // Get the default or comprehensive assessment
        $assessment = Assessment::where('is_active', true)->first();

        if (! $assessment) {
            return $this->sendError('No active assessment found.');
        }

        // Load questions
        $assessment->load('questions');

        return $this->sendResponse([
            'total_questions' => $assessment->questions->count(),
            'questions' => $assessment->questions,
        ], 'Assessment questions retrieved successfully.');
    }

    public function retake(Request $request)
    {
        // Logic to maybe archive old result or just allow new submission
        // For now, we delegate to store which creates a new result
        return $this->store($request);
    }

    public function show($id)
    {
        // Accept both numeric ID and UUID
        $assessment = Assessment::with('questions')
            ->where('uuid', $id)
            ->orWhere('id', $id)
            ->first();

        if (! $assessment) {
            return $this->sendError('Assessment not found.');
        }

        return $this->sendResponse($assessment, 'Assessment details retrieved successfully.');
    }

    /**
     * Submit assessment answers using UUID from route parameter.
     * Frontend calls POST /patient/assessments/{uuid}/submit with { answers: [...] }.
     */
    public function submitByUuid(Request $request, string $uuid)
    {
        $assessment = Assessment::where('uuid', $uuid)->first();

        if (! $assessment) {
            return $this->sendError('Assessment not found.', [], 404);
        }

        // Inject assessment_id so the existing store() validation passes
        $request->merge(['assessment_id' => $assessment->id]);

        return $this->store($request);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assessment_id' => 'required|exists:assessments,id',
            'answers' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $user = $request->user();
        $assessment = Assessment::with(['questions' => function ($q) {
            $q->orderBy('order_number');
        }])->find($request->assessment_id);

        // Calculate score with instrument-specific rules
        $title = strtolower(trim($assessment->title ?? ''));
        $rawAnswers = $request->answers;

        // Normalize: frontend may send [{ question_id, answer }] or { id: value }
        $answers = [];
        if (is_array($rawAnswers) && isset($rawAnswers[0]) && is_array($rawAnswers[0]) && array_key_exists('question_id', $rawAnswers[0])) {
            // Array-of-objects format from frontend: [{ question_id: 1, answer: 2 }, ...]
            foreach ($rawAnswers as $item) {
                $answers[(int) ($item['question_id'] ?? 0)] = $item['answer'] ?? 0;
            }
        } else {
            // Associative format: { "1": 2, "2": 3, ... }
            $answers = $rawAnswers;
        }

        $questionOrderMap = [];
        foreach ($assessment->questions as $idx => $q) {
            $questionOrderMap[$q->id] = $idx + 1; // 1-based index
        }

        $rawScore = 0;
        foreach ($answers as $questionId => $value) {
            $v = is_numeric($value) ? (int) $value : 0;
            // PSS-10 reverse scoring for items 4,5,7,8 (1-based indices)
            if (strpos($title, 'pss') !== false || strpos($title, 'perceived stress') !== false) {
                $pos = $questionOrderMap[(int) $questionId] ?? null;
                if (in_array($pos, [4, 5, 7, 8], true)) {
                    $v = 4 - max(0, min(4, $v));
                }
            }
            $rawScore += $v;
        }

        // Determine max score and percentage
        $maxScore = null;
        if (strpos($title, 'phq-9') !== false || strpos($title, 'phq9') !== false) {
            $maxScore = 27;
        } elseif (strpos($title, 'gad-7') !== false || strpos($title, 'gad7') !== false) {
            $maxScore = 21;
        } elseif (strpos($title, 'pss-10') !== false || strpos($title, 'perceived stress') !== false) {
            $maxScore = 40;
        } elseif (strpos($title, 'who-5') !== false || strpos($title, 'well-being') !== false) {
            $maxScore = 25;
        } else {
            // Fallback to question-defined scales if available
            $maxScore = 0;
            foreach ($assessment->questions as $q) {
                $min = is_numeric($q->scale_min) ? (int) $q->scale_min : 0;
                $max = is_numeric($q->scale_max) ? (int) $q->scale_max : 0;
                $maxScore += max(0, $max - $min);
            }
            if ($maxScore === 0) {
                $maxScore = 100;
            }
        }

        // Compute percentage
        $percentage = $maxScore > 0 ? round(($rawScore / $maxScore) * 100, 2) : 0.0;

        // WHO-5 conventionally reported as 0-100 transformed score
        if (strpos($title, 'who-5') !== false || strpos($title, 'well-being') !== false) {
            $percentage = round(($rawScore / 25) * 100, 2);
        }

        // Severity classification (human-readable label)
        $severityLabel = null;
        if (strpos($title, 'phq-9') !== false || strpos($title, 'phq9') !== false) {
            $severityLabel = match (true) {
                $rawScore >= 20 => 'Severe',
                $rawScore >= 15 => 'Moderately Severe',
                $rawScore >= 10 => 'Moderate',
                $rawScore >= 5 => 'Mild',
                default => 'Minimal',
            };
        } elseif (strpos($title, 'gad-7') !== false || strpos($title, 'gad7') !== false) {
            $severityLabel = match (true) {
                $rawScore >= 15 => 'Severe',
                $rawScore >= 10 => 'Moderate',
                $rawScore >= 5 => 'Mild',
                default => 'Minimal',
            };
        } elseif (strpos($title, 'pss-10') !== false || strpos($title, 'perceived stress') !== false) {
            $severityLabel = match (true) {
                $rawScore >= 27 => 'High',
                $rawScore >= 14 => 'Moderate',
                default => 'Low',
            };
        } elseif (strpos($title, 'who-5') !== false || strpos($title, 'well-being') !== false) {
            $severityLabel = match (true) {
                $percentage > 75 => 'High Well-being',
                $percentage > 50 => 'Moderate Well-being',
                default => 'Low Well-being',
            };
        }

        // Generate AI interpretation with severity and percentage context
        $aiAnalysis = $this->aiService->generateAssessmentInterpretation(
            $assessment->title ?? 'Assessment',
            $rawScore,
            $answers,
            $percentage,
            $severityLabel
        );

        try {
            $result = UserAssessmentResult::create([
                'user_id' => $user->id,
                'assessment_id' => $assessment->id,
                'answers' => $answers,
                'total_score' => $rawScore,
                'severity_level' => $severityLabel,
                'completed_at' => now(),
                'interpretation' => $aiAnalysis['interpretation'] ?? null,
                'recommendations' => $aiAnalysis['recommendations'] ?? null,
            ]);

            // Update user's matching preferences based on this assessment
            $this->matchingService->updatePreferencesFromAssessment($user, $result);

            // Fire event — listener decides intelligently whether to email based on severity
            AssessmentCompleted::dispatch($result, $assessment);

        } catch (\Exception $e) {
            // log and return friendly error so frontend sees message instead of 500
            Log::error('Assessment: store() failed', [
                'user_id' => $user->id ?? null,
                'assessment_id' => $assessment->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save assessment results',
            ], 500);
        }

        // Include percentage, max_score and original severity label in response
        $payload = array_merge($result->toArray(), [
            'percentage' => $percentage,
            'max_score' => $maxScore,
            'severity_level' => $severityLabel,
        ]);

        return $this->sendResponse($payload, 'Assessment submitted successfully.');
    }

    public function history(Request $request)
    {
        $results = UserAssessmentResult::where('user_id', $request->user()->id)
            ->with('assessment')
            ->orderBy('completed_at', 'desc')
            ->get();

        return $this->sendResponse($results, 'Assessment history retrieved successfully.');
    }

    public function categories(Request $request)
    {
        $categories = Assessment::where('is_active', true)
            ->whereNotNull('type')
            ->distinct()
            ->pluck('type')
            ->values();

        return $this->sendResponse($categories, 'Assessment categories retrieved successfully.');
    }
}
