<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\MoodLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MoodController extends BaseController
{
    public function stats(Request $request)
    {
        if (! $request->user()->patient) {
            return $this->sendError('User is not a patient profile.', [], 403);
        }

        $period = $request->input('period', 'week');

        // Use real data if available, otherwise fallback to mock for now if DB is empty
        $logs = MoodLog::where('patient_id', $request->user()->patient->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'asc')
            ->get();

        if ($logs->isEmpty()) {
            // Return empty state with helpful message
            $stats = [
                'current_mood' => null,
                'average_mood' => null,
                'mood_distribution' => [],
                'daily_timeline' => [],
                'weekly_trend' => [],
                'message' => 'Start logging your mood to see insights and trends.',
                'has_data' => false,
            ];

            return $this->sendResponse($stats, 'No mood data available yet.');
        }

        // Real calculation
        $daily = $logs->groupBy(function ($item) {
            return $item->created_at->format('Y-m-d');
        })->map(function ($dayLogs) {
            return [
                'date' => $dayLogs->first()->created_at->format('Y-m-d'),
                'avg_score' => round($dayLogs->avg('mood_score'), 1),
                'count' => $dayLogs->count(),
                'emotions' => $dayLogs->pluck('emotions')->flatten()->filter()->countBy()->sortDesc()->take(3)->keys(),
            ];
        })->values();

        return $this->sendResponse([
            'daily_trend' => $daily,
            'overall_avg' => round($logs->avg('mood_score'), 1),
            'total_logs' => $logs->count(),
            'has_data' => true,
            'message' => 'Mood statistics retrieved successfully.',
        ], 'Mood stats retrieved.');
    }

    public function aiSuggestions(Request $request)
    {
        // Get recent mood data for personalized suggestions
        if (! $request->user()->patient) {
            return $this->sendError('User is not a patient profile.', [], 403);
        }

        $recentMood = MoodLog::where('patient_id', $request->user()->patient->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->first();

        $suggestions = [];

        if ($recentMood && $recentMood->mood_score <= 4) {
            // Low mood suggestions
            $suggestions = [
                ['title' => 'Take a walk', 'type' => 'activity', 'reason' => 'Physical activity boosts serotonin levels'],
                ['title' => 'Deep breathing', 'type' => 'mindfulness', 'reason' => 'Helps reduce anxiety and stress'],
                ['title' => 'Call a friend', 'type' => 'social', 'reason' => 'Social connection improves mood'],
                ['title' => 'Listen to music', 'type' => 'activity', 'reason' => 'Music can elevate mood and reduce stress'],
            ];
        } elseif ($recentMood && $recentMood->mood_score >= 8) {
            // High mood suggestions
            $suggestions = [
                ['title' => 'Share positivity', 'type' => 'social', 'reason' => 'Share your good mood with others'],
                ['title' => 'Plan something fun', 'type' => 'activity', 'reason' => 'Maintain momentum with future plans'],
                ['title' => 'Practice gratitude', 'type' => 'mindfulness', 'reason' => 'Reinforce positive thinking'],
                ['title' => 'Help someone', 'type' => 'social', 'reason' => 'Acts of kindness boost happiness'],
            ];
        } else {
            // General suggestions
            $suggestions = [
                ['title' => 'Take a walk', 'type' => 'activity', 'reason' => 'Physical activity boosts serotonin levels'],
                ['title' => 'Deep breathing', 'type' => 'mindfulness', 'reason' => 'Helps reduce anxiety and stress'],
                ['title' => 'Stay hydrated', 'type' => 'health', 'reason' => 'Proper hydration affects mood and energy'],
                ['title' => 'Get some sunlight', 'type' => 'activity', 'reason' => 'Natural light improves mood and vitamin D'],
            ];
        }

        return $this->sendResponse($suggestions, 'AI mood suggestions retrieved.');
    }

    public function index(Request $request)
    {
        // Ensure user is a patient
        if (! $request->user()->patient) {
            return $this->sendError('User is not a patient profile.', [], 403);
        }

        $query = MoodLog::where('patient_id', $request->user()->patient->id)
            ->orderBy('created_at', 'desc');

        $fromDate = $request->input('from_date') ?? $request->input('start_date');
        $toDate   = $request->input('to_date')   ?? $request->input('end_date');
        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        return $this->sendResponse($query->paginate(20), 'Mood logs retrieved.');
    }

    public function store(Request $request)
    {
        // Ensure user is a patient
        if (! $request->user()->patient) {
            return $this->sendError('User is not a patient profile.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'mood_score' => 'required|integer|min:1|max:10',
            'emotions' => 'nullable|array',
            'activities' => 'nullable|array',
            'notes' => 'nullable|string',
            'sleep_hours' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $log = MoodLog::create([
            'user_id' => $request->user()->id,
            'patient_id' => $request->user()->patient->id,
            'mood_score' => $request->mood_score,
            'emotions' => $request->emotions,
            'activities' => $request->activities,
            'notes' => $request->notes,
            'sleep_hours' => $request->sleep_hours,
            'weather_data' => null, // Could fetch API here
        ]);

        return $this->sendResponse($log, 'Mood logged successfully.');
    }

    public function show($id)
    {
        $log = MoodLog::where('patient_id', request()->user()->patient->id)->find($id);

        if (! $log) {
            return $this->sendError('Mood log not found.');
        }

        return $this->sendResponse($log, 'Mood log retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $log = MoodLog::where('patient_id', request()->user()->patient->id)->find($id);

        if (! $log) {
            return $this->sendError('Mood log not found.');
        }

        $log->update($request->all());

        return $this->sendResponse($log, 'Mood log updated successfully.');
    }

    public function destroy($id)
    {
        $log = MoodLog::where('patient_id', request()->user()->patient->id)->find($id);

        if (! $log) {
            return $this->sendError('Mood log not found.');
        }

        $log->delete();

        return $this->sendResponse([], 'Mood log deleted successfully.');
    }

    // Duplicate stats method removed
}
