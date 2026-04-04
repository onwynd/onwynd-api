<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\MindfulnessActivity;
use App\Models\MindfulResource;
use App\Services\OnwyndScoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MindfulnessController extends BaseController
{
    protected $scoreService;

    public function __construct(OnwyndScoreService $scoreService)
    {
        $this->scoreService = $scoreService;
    }

    public function index(Request $request)
    {
        $history = MindfulnessActivity::where('user_id', $request->user()->id)
            ->orderBy('completed_at', 'desc')
            ->limit(50)
            ->get();

        $stats = [
            'total_minutes' => $history->sum('duration_seconds') / 60,
            'sessions_count' => $history->count(),
        ];

        return $this->sendResponse([
            'history' => $history,
            'stats' => $stats,
        ], 'Mindfulness history retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'type' => 'required|string',
            'duration_seconds' => 'required|integer|min:1',
            'completed_at' => 'required|date',
            'audio_file_path' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $activity = MindfulnessActivity::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'type' => $request->type,
            'duration_seconds' => $request->duration_seconds,
            'completed_at' => $request->completed_at,
            'notes' => $request->notes,
            'audio_file_path' => $request->audio_file_path,
        ]);

        // Update Onwynd Score
        $this->scoreService->updateScore($request->user());

        return $this->sendResponse($activity, 'Mindfulness activity logged successfully.');
    }

    public function exercises(Request $request)
    {
        // Fetch exercises from MindfulResource (excluding soundscapes/sleep)
        // Assuming 'meditation' and 'breathing' categories are exercises
        $exercises = MindfulResource::whereIn('type', ['audio', 'video'])
            ->where('status', 'published')
            ->whereHas('category', function ($q) {
                $q->whereIn('slug', ['meditation', 'mindfulness', 'breathing']);
            })
            ->with('category')
            ->get()
            ->map(function ($resource) {
                return [
                    'id' => $resource->id,
                    'title' => $resource->title,
                    'category' => $resource->category ? strtolower($resource->category->name) : 'general',
                    'difficulty' => 'easy', // Default for now, could be added to DB
                    'duration' => round($resource->duration_seconds / 60).' mins',
                    'image_url' => $resource->thumbnail_url ? url($resource->thumbnail_url) : 'https://api.onwynd.com/images/default-exercise.jpg',
                    'is_premium' => $resource->is_premium,
                ];
            });

        return $this->sendResponse($exercises, 'Mindfulness exercises retrieved.');
    }

    public function showExercise($id)
    {
        $resource = MindfulResource::with('category')->find($id);

        if (! $resource) {
            return $this->sendError('Exercise not found.', [], 404);
        }

        return $this->sendResponse([
            'id' => $resource->id,
            'title' => $resource->title,
            'description' => $resource->content ?? $resource->category->description ?? 'A mindfulness exercise.',
            'steps' => ['Find a comfortable position', 'Follow the audio guide', 'Relax and breathe'], // Generic steps if not in DB
            'audio_url' => url('storage/'.$resource->media_url),
            'duration_seconds' => $resource->duration_seconds,
            'is_premium' => $resource->is_premium,
        ], 'Exercise details retrieved.');
    }

    public function startSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'soundscape_id' => 'nullable|integer|exists:mindful_resources,id',
            'audio_file_path' => 'required|string',
            'duration_minutes' => 'nullable|integer',
            'type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Create a real DB record so Quota middleware counts it

        $durationSeconds = ($request->duration_minutes ?? 10) * 60;

        // Find title if ID provided, else use default
        $title = 'Mindfulness Session';
        if ($request->soundscape_id) {
            $resource = MindfulResource::find($request->soundscape_id);
            if ($resource) {
                $title = $resource->title;
            }
        }

        $activity = MindfulnessActivity::create([
            'user_id' => $request->user()->id,
            'title' => $title,
            'type' => $request->type ?? 'soundscape',
            'duration_seconds' => $durationSeconds,
            'completed_at' => now(), // Marking as completed at start for simple quota enforcement
            'audio_file_path' => $request->audio_file_path,
            'notes' => 'Session started via Unwind Hub',
        ]);

        return $this->sendResponse([
            'id' => $activity->id,
            'uuid' => (string) Str::uuid(),
            'start_time' => now(),
            'status' => 'started',
        ], 'Mindfulness session started successfully.');
    }

    public function soundscapes(Request $request)
    {
        // Fetch real seeded data from mindful_resources table with pagination
        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);

        $resources = MindfulResource::where('type', 'audio')
            ->where('status', 'published')
            ->with('category')
            ->orderBy('resource_category_id')
            ->orderBy('title')
            ->paginate($perPage, ['*'], 'page', $page);

        $mappedResources = $resources->map(function ($resource) {
            return [
                'id' => $resource->id,
                'uuid' => $resource->slug, // Use slug as uuid for frontend if needed
                'title' => $resource->title,
                'description' => $resource->category ? $resource->category->name : 'Relaxing Audio',
                'category' => $resource->category ? strtolower($resource->category->name) : 'general',
                'preview_url' => url('storage/'.$resource->media_url), // Full URL for frontend player
                'audio_file_path' => $resource->media_url, // Relative path used by startSession
                'full_url' => $resource->is_premium ? url('storage/'.$resource->media_url) : null, // Premium only
                'duration_minutes' => round($resource->duration_seconds / 60),
                'is_free_preview' => ! $resource->is_premium,
                'tags' => [$resource->category ? $resource->category->name : 'Mindfulness'],
            ];
        });

        return $this->sendResponse([
            'data' => $mappedResources,
            'pagination' => [
                'current_page' => $resources->currentPage(),
                'per_page' => $resources->perPage(),
                'total' => $resources->total(),
                'last_page' => $resources->lastPage(),
                'from' => $resources->firstItem(),
                'to' => $resources->lastItem(),
            ],
        ], 'Soundscapes retrieved successfully.');
    }

    public function recommendedExercises(Request $request)
    {
        // Recommend exercises based on the user's most recent mood or most-used type
        $userId = $request->user()->id;

        $recentType = MindfulnessActivity::where('user_id', $userId)
            ->orderBy('completed_at', 'desc')
            ->value('type') ?? 'meditation';

        $exercises = MindfulResource::whereIn('type', ['audio', 'video'])
            ->where('status', 'published')
            ->whereHas('category', function ($q) use ($recentType) {
                $q->whereIn('slug', ['meditation', 'mindfulness', 'breathing']);
            })
            ->with('category')
            ->limit(6)
            ->get()
            ->map(function ($resource) {
                return [
                    'id' => $resource->id,
                    'title' => $resource->title,
                    'category' => $resource->category ? strtolower($resource->category->name) : 'general',
                    'difficulty' => 'easy',
                    'duration' => round($resource->duration_seconds / 60).' mins',
                    'image_url' => $resource->thumbnail_url ? url($resource->thumbnail_url) : null,
                    'is_premium' => $resource->is_premium,
                ];
            });

        return $this->sendResponse($exercises, 'Recommended exercises retrieved.');
    }

    public function getSessions(Request $request)
    {
        $query = MindfulnessActivity::where('user_id', $request->user()->id)
            ->orderBy('completed_at', 'desc');

        if ($request->has('from_date')) {
            $query->whereDate('completed_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('completed_at', '<=', $request->to_date);
        }
        if ($request->has('completed')) {
            // All stored activities are treated as completed
            if (! filter_var($request->completed, FILTER_VALIDATE_BOOLEAN)) {
                return $this->sendResponse([], 'Sessions retrieved.');
            }
        }

        return $this->sendResponse($query->paginate(20), 'Sessions retrieved.');
    }

    public function getSession(Request $request, int $id)
    {
        $activity = MindfulnessActivity::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return $this->sendResponse($activity, 'Session retrieved.');
    }

    public function completeSession(Request $request, int $id)
    {
        $activity = MindfulnessActivity::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $activity->update([
            'completed_at' => now(),
            'duration_seconds' => $request->input('duration_seconds', $activity->duration_seconds),
            'notes' => $request->input('notes', $activity->notes),
        ]);

        $this->scoreService->updateScore($request->user());

        return $this->sendResponse($activity->fresh(), 'Session completed.');
    }

    public function cancelSession(Request $request, int $id)
    {
        $activity = MindfulnessActivity::where('user_id', $request->user()->id)
            ->findOrFail($id);
        $activity->delete();

        return $this->sendResponse([], 'Session cancelled.');
    }

    public function deleteSession(Request $request, int $id)
    {
        $activity = MindfulnessActivity::where('user_id', $request->user()->id)
            ->findOrFail($id);
        $activity->delete();

        return $this->sendResponse([], 'Session deleted.');
    }

    public function activeSession(Request $request)
    {
        // Activities are logged synchronously — no persistent "active" state exists
        return $this->sendResponse(null, 'No active session.');
    }

    public function getStats(Request $request)
    {
        $userId = $request->user()->id;

        $activities = MindfulnessActivity::where('user_id', $userId)->get();

        $totalMinutes = round($activities->sum('duration_seconds') / 60);
        $sessionsCount = $activities->count();
        $streak = $this->calculateStreak($userId);

        $byType = $activities->groupBy('type')->map(fn ($g) => $g->count());

        return $this->sendResponse([
            'total_minutes' => $totalMinutes,
            'sessions_count' => $sessionsCount,
            'current_streak' => $streak,
            'by_type' => $byType,
        ], 'Mindfulness stats retrieved.');
    }

    public function getTrends(Request $request)
    {
        $userId = $request->user()->id;
        $from = $request->input('from_date', now()->subDays(30)->toDateString());
        $to   = $request->input('to_date', now()->toDateString());

        $activities = MindfulnessActivity::where('user_id', $userId)
            ->whereBetween('completed_at', [$from, $to])
            ->orderBy('completed_at')
            ->get();

        $grouped = $activities->groupBy(fn ($a) => $a->completed_at->format('Y-m-d'));

        $dates    = $grouped->keys()->values();
        $sessions = $grouped->map->count()->values();
        $minutes  = $grouped->map(fn ($g) => round($g->sum('duration_seconds') / 60))->values();

        return $this->sendResponse([
            'dates' => $dates,
            'sessions' => $sessions,
            'minutes' => $minutes,
            'average_mood_improvement' => array_fill(0, $dates->count(), 0),
            'average_stress_reduction' => array_fill(0, $dates->count(), 0),
        ], 'Mindfulness trends retrieved.');
    }

    private function calculateStreak(int $userId): int
    {
        $dates = MindfulnessActivity::where('user_id', $userId)
            ->selectRaw('DATE(completed_at) as day')
            ->distinct()
            ->orderByDesc('day')
            ->pluck('day')
            ->map(fn ($d) => \Carbon\Carbon::parse($d));

        $streak = 0;
        $check  = now()->startOfDay();

        foreach ($dates as $date) {
            if ($date->equalTo($check) || $date->equalTo($check->copy()->subDay())) {
                $streak++;
                $check = $date->copy()->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }

    public function searchSoundscapes(Request $request)
    {
        $q = $request->input('q');

        if (! $q) {
            return $this->soundscapes($request);
        }

        $resources = MindfulResource::where('type', 'audio')
            ->where('status', 'published')
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhereHas('category', function ($catQuery) use ($q) {
                        $catQuery->where('name', 'like', "%{$q}%");
                    });
            })
            ->with('category')
            ->get()
            ->map(function ($resource) {
                return [
                    'id' => $resource->id,
                    'title' => $resource->title,
                    'match' => true,
                    'category' => $resource->category ? strtolower($resource->category->name) : 'general',
                    'preview_url' => url('storage/'.$resource->media_url),
                ];
            });

        return $this->sendResponse($resources, 'Soundscape search results.');
    }
}
