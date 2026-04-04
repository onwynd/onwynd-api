<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\AudioListen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AudioListenController extends BaseController
{
    public function store(Request $request)
    {
        // Fire-and-forget  always return 200 regardless of outcome
        try {
            $validator = Validator::make($request->all(), [
                'track_id' => 'required|string|max:255',
                'track_title' => 'required|string|max:255',
                'track_category' => 'required|in:mindfulness,affirmation,sleep,grief,nature,ambient,other',
                'duration_seconds' => 'nullable|integer|min:0',
                'completed' => 'nullable|boolean',
            ]);

            if (! $validator->fails()) {
                AudioListen::create([
                    'user_id' => $request->user()->id,
                    'track_id' => $request->track_id,
                    'track_title' => $request->track_title,
                    'track_category' => $request->track_category,
                    'duration_seconds' => $request->integer('duration_seconds', 0),
                    'completed' => $request->boolean('completed', false),
                    'listened_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('AudioListen store silently failed', ['error' => $e->getMessage()]);
        }

        // Always 200  fire and forget
        return response()->json(['success' => true]);
    }

    public function history(Request $request)
    {
        $user = $request->user();
        $since = now()->subDays(30);

        try {
            // Listens grouped by category in the last 30 days
            $byCategory = DB::table('audio_listens')
                ->where('user_id', $user->id)
                ->where('listened_at', '>=', $since)
                ->selectRaw('track_category, COUNT(*) as listen_count, SUM(duration_seconds) as total_seconds, SUM(completed::int) as completed_count')
                ->groupBy('track_category')
                ->orderByDesc('listen_count')
                ->get();

            // Attempt to correlate with mood logs
            $listens = DB::table('audio_listens')
                ->where('user_id', $user->id)
                ->where('listened_at', '>=', $since)
                ->orderByDesc('listened_at')
                ->get(['id', 'track_id', 'track_title', 'track_category', 'duration_seconds', 'completed', 'listened_at']);

            $listensWithMood = $listens->map(function ($listen) use ($user) {
                $listenTime = \Carbon\Carbon::parse($listen->listened_at);
                $moodAfter = DB::table('mood_logs')
                    ->where('user_id', $user->id)
                    ->whereBetween('created_at', [$listenTime, $listenTime->copy()->addHours(2)])
                    ->orderBy('created_at')
                    ->value('mood_score');

                return array_merge((array) $listen, ['mood_after' => $moodAfter]);
            });

            // Days with listening vs without (mood avg comparison)
            $daysWithListening = DB::table('audio_listens')
                ->where('user_id', $user->id)
                ->where('listened_at', '>=', $since)
                ->selectRaw('DATE(listened_at) as date')
                ->groupBy(DB::raw('DATE(listened_at)'))
                ->pluck('date')
                ->toArray();

            $totalListens = count($listens);
            $mostListenedCategory = $byCategory->first()?->track_category ?? null;

            $moodWithListening = count($daysWithListening) > 0
                ? DB::table('mood_logs')
                    ->where('user_id', $user->id)
                    ->where('created_at', '>=', $since)
                    ->whereRaw('DATE(created_at) IN ('.implode(',', array_map(fn ($d) => "'$d'", $daysWithListening)).')')
                    ->avg('mood_score')
                : null;

            $moodWithoutListening = count($daysWithListening) > 0
                ? DB::table('mood_logs')
                    ->where('user_id', $user->id)
                    ->where('created_at', '>=', $since)
                    ->whereRaw('DATE(created_at) NOT IN ('.implode(',', array_map(fn ($d) => "'$d'", $daysWithListening)).')')
                    ->avg('mood_score')
                : null;

            return $this->sendResponse([
                'total_listens' => $totalListens,
                'by_category' => $byCategory,
                'most_listened_category' => $mostListenedCategory,
                'mood_avg_with_listening' => $moodWithListening ? round($moodWithListening, 2) : null,
                'mood_avg_without_listening' => $moodWithoutListening ? round($moodWithoutListening, 2) : null,
                'listens' => $listensWithMood->take(50)->values(),
            ], 'Audio history retrieved.');
        } catch (\Throwable $e) {
            Log::error('AudioListen history failed', ['error' => $e->getMessage()]);

            return $this->sendError('Failed to retrieve audio history.', [], 500);
        }
    }
}
