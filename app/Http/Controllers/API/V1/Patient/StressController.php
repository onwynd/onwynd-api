<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\StressAssessment;
use App\Services\OnwyndScoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StressController extends BaseController
{
    protected $scoreService;

    public function __construct(OnwyndScoreService $scoreService)
    {
        $this->scoreService = $scoreService;
    }

    public function index(Request $request)
    {
        $query = StressAssessment::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        return $this->sendResponse($query->paginate(20), 'Stress history retrieved successfully.');
    }

    public function overview(Request $request)
    {
        $user = $request->user();

        $latest = StressAssessment::where('user_id', $user->id)->latest()->first();
        $recent = StressAssessment::where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        // Calculate average stress of last 7 days
        $avgStress = StressAssessment::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->avg('stress_level');

        return $this->sendResponse([
            'current_level' => $latest ? $latest->stress_level : null,
            'status' => $this->getStressStatus($latest ? $latest->stress_level : 0),
            'weekly_average' => round($avgStress, 1),
            'recent_assessments' => $recent,
        ], 'Stress overview retrieved.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'stress_level' => 'required|integer|min:1|max:10',
            'stressors' => 'nullable|array',
            'symptoms' => 'nullable|array',
            'notes' => 'nullable|string',
            'facial_image' => 'nullable|string', // Base64 or URL if already uploaded
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $assessment = StressAssessment::create([
            'user_id' => $request->user()->id,
            'stress_level' => $request->stress_level,
            'stressors' => $request->stressors,
            'symptoms' => $request->symptoms,
            'notes' => $request->notes,
            // 'facial_image_url' => handle upload if needed
        ]);

        // Update Onwynd Score
        $this->scoreService->updateScore($request->user());

        return $this->sendResponse($assessment, 'Stress assessment recorded successfully.');
    }

    public function trends(Request $request)
    {
        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to = $request->input('to', now()->toDateString());

        $logs = StressAssessment::where('user_id', $request->user()->id)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->orderBy('created_at', 'asc')
            ->get();

        $dates = $logs->map(fn ($l) => $l->created_at->toDateString())->toArray();
        $levels = $logs->map(fn ($l) => $l->stress_level)->toArray();

        // Aggregate trigger frequencies
        $triggerFreq = [];
        foreach ($logs as $log) {
            foreach ((array) ($log->stressors ?? []) as $trigger) {
                $triggerFreq[$trigger] = ($triggerFreq[$trigger] ?? 0) + 1;
            }
        }
        arsort($triggerFreq);

        return $this->sendResponse([
            'dates' => $dates,
            'stress_levels' => $levels,
            'trigger_frequencies' => $triggerFreq,
        ], 'Stress trends retrieved.');
    }

    public function copingStrategies(Request $request)
    {
        $triggers = $request->input('triggers', []);

        // Curated strategy map keyed by common stressor keywords
        $strategyMap = [
            'work' => ['Time-boxing tasks', 'Take a 5-minute break every hour', 'Prioritise using the Eisenhower matrix'],
            'relationships' => ['Practice active listening', 'Schedule quality time with loved ones', 'Set healthy boundaries'],
            'finances' => ['Create a monthly budget', 'Speak to a financial advisor', 'Focus on what you can control today'],
            'health' => ['30-minute daily walk', 'Prioritise 7-8 hours of sleep', 'Limit caffeine and alcohol'],
            'default' => ['5-4-3-2-1 grounding exercise', 'Box breathing (4-4-4-4)', 'Progressive muscle relaxation', 'Write in your journal', 'Reach out to a friend or therapist'],
        ];

        $strategies = collect($strategyMap['default']);
        foreach ($triggers as $trigger) {
            $key = strtolower($trigger);
            foreach ($strategyMap as $mapKey => $mapStrategies) {
                if ($mapKey !== 'default' && str_contains($key, $mapKey)) {
                    $strategies = $strategies->merge($mapStrategies);
                }
            }
        }

        return $this->sendResponse([
            'strategies' => $strategies->unique()->values(),
            'resources' => [
                ['title' => 'Mindfulness Exercises', 'description' => 'Short guided exercises to reduce stress'],
                ['title' => 'Breathing Techniques', 'description' => 'Proven breathing patterns for calm'],
            ],
        ], 'Coping strategies retrieved.');
    }

    public function commonSymptoms()
    {
        return $this->sendResponse([
            'Headaches',
            'Muscle tension',
            'Fatigue',
            'Sleep disturbances',
            'Difficulty concentrating',
            'Irritability',
            'Stomach upset',
            'Rapid heartbeat',
            'Sweating',
            'Changes in appetite',
        ], 'Common stress symptoms retrieved.');
    }

    private function getStressStatus($level)
    {
        if ($level <= 3) {
            return 'Low';
        }
        if ($level <= 7) {
            return 'Moderate';
        }

        return 'High';
    }
}
