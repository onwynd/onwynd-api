<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Mail\WellnessDataExport;
use App\Models\Gamification\Streak;
use App\Models\JournalEntry;
use App\Models\MoodLog;
use App\Models\SleepLog;
use App\Models\UserAssessmentResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class WellnessController extends BaseController
{
    /**
     * GET /patient/wellness/dashboard
     * Aggregated snapshot of all wellness pillars.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $since = now()->subDays(7);

        $moodLogs = MoodLog::where('user_id', $user->id)->where('created_at', '>=', $since)->get();
        $sleepLogs = SleepLog::where('user_id', $user->id)->where('created_at', '>=', $since)->get();
        $streak = Streak::where('user_id', $user->id)->first();
        $journalCount = JournalEntry::where('user_id', $user->id)->where('created_at', '>=', $since)->count();

        $avgMood = $moodLogs->avg('mood_score') ?? 0;
        $avgSleep = $sleepLogs->avg('duration_minutes') ? round($sleepLogs->avg('duration_minutes') / 60, 1) : 0;
        $avgSleepQuality = $sleepLogs->avg('quality_rating') ?? 0;

        return $this->sendResponse([
            'period_days' => 7,
            'mood' => [
                'average_score' => round($avgMood, 1),
                'logs_count' => $moodLogs->count(),
                'scale' => '1-10',
            ],
            'sleep' => [
                'average_hours' => $avgSleep,
                'average_quality' => round($avgSleepQuality, 1),
                'logs_count' => $sleepLogs->count(),
            ],
            'streak' => [
                'current' => $streak?->current_streak ?? 0,
                'longest' => $streak?->longest_streak ?? 0,
            ],
            'journal' => [
                'entries_this_week' => $journalCount,
            ],
            'wellness_score' => $this->computeScore($user),
        ], 'Wellness dashboard retrieved successfully.');
    }

    /**
     * GET /patient/wellness/score
     * Single numeric wellness score (0–100).
     */
    public function score(Request $request)
    {
        $score = $this->computeScore($request->user());

        return $this->sendResponse([
            'score' => $score,
            'label' => $this->scoreLabel($score),
            'max' => 100,
        ], 'Wellness score retrieved successfully.');
    }

    /**
     * GET /patient/wellness/recommendations
     * Personalised recommendations based on recent activity.
     */
    public function recommendations(Request $request)
    {
        $user = $request->user();
        $since = now()->subDays(7);
        $tips = [];

        $avgMood = MoodLog::where('user_id', $user->id)->where('created_at', '>=', $since)->avg('mood_score') ?? 0;
        if ($avgMood < 5) {
            $tips[] = ['type' => 'mood',      'message' => 'Your mood has been low this week. Try a 5-minute mindfulness session.'];
        }

        $avgSleepHours = SleepLog::where('user_id', $user->id)->where('created_at', '>=', $since)->avg('duration_minutes') / 60 ?? 0;
        if ($avgSleepHours < 7) {
            $tips[] = ['type' => 'sleep',     'message' => 'You\'re averaging under 7 hours of sleep. Try setting a consistent bedtime.'];
        }

        $streak = Streak::where('user_id', $user->id)->first();
        if (! $streak || $streak->current_streak < 3) {
            $tips[] = ['type' => 'streak',    'message' => 'Build your streak by checking in daily. Even 2 minutes counts!'];
        }

        $journalCount = JournalEntry::where('user_id', $user->id)->where('created_at', '>=', $since)->count();
        if ($journalCount === 0) {
            $tips[] = ['type' => 'journal',   'message' => 'You haven\'t journaled this week. Writing even one sentence can lift your mood.'];
        }

        if (empty($tips)) {
            $tips[] = ['type' => 'general',   'message' => 'Great work this week! Keep up your healthy habits.'];
        }

        return $this->sendResponse($tips, 'Recommendations retrieved successfully.');
    }

    /**
     * POST /patient/wellness/check-in
     * Quick wellness check-in stored as a mood log.
     */
    public function checkIn(Request $request)
    {
        $data = $request->validate([
            'mood_score' => 'required|integer|min:1|max:10',
            'emotions' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
            'energy_level' => 'nullable|integer|min:1|max:10',
        ]);

        $log = MoodLog::create([
            'user_id' => $request->user()->id,
            'mood_score' => $data['mood_score'],
            'emotions' => $data['emotions'] ?? [],
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->sendResponse([
            'id' => $log->id,
            'mood_score' => $log->mood_score,
            'logged_at' => $log->created_at->toISOString(),
        ], 'Wellness check-in recorded successfully.');
    }

    /**
     * GET /patient/wellness/check-in/history
     * Recent mood log history (last 30 days).
     */
    public function checkInHistory(Request $request)
    {
        $logs = MoodLog::where('user_id', $request->user()->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->get(['id', 'mood_score', 'emotions', 'notes', 'created_at']);

        return $this->sendResponse($logs, 'Check-in history retrieved successfully.');
    }

    /**
     * GET /patient/wellness/insights
     * Trend analysis across the last 28 days vs. previous 28 days.
     */
    public function insights(Request $request)
    {
        $user = $request->user();
        $recent = now()->subDays(28);
        $prior = now()->subDays(56);

        $recentMood = MoodLog::where('user_id', $user->id)->where('created_at', '>=', $recent)->avg('mood_score') ?? 0;
        $priorMood = MoodLog::where('user_id', $user->id)->whereBetween('created_at', [$prior, $recent])->avg('mood_score') ?? 0;

        $recentSleep = SleepLog::where('user_id', $user->id)->where('created_at', '>=', $recent)->avg('quality_rating') ?? 0;
        $priorSleep = SleepLog::where('user_id', $user->id)->whereBetween('created_at', [$prior, $recent])->avg('quality_rating') ?? 0;

        return $this->sendResponse([
            'mood_trend' => [
                'recent_avg' => round($recentMood, 1),
                'prior_avg' => round($priorMood, 1),
                'direction' => $recentMood >= $priorMood ? 'improving' : 'declining',
            ],
            'sleep_trend' => [
                'recent_avg' => round($recentSleep, 1),
                'prior_avg' => round($priorSleep, 1),
                'direction' => $recentSleep >= $priorSleep ? 'improving' : 'declining',
            ],
            'wellness_score' => $this->computeScore($user),
        ], 'Wellness insights retrieved successfully.');
    }

    /**
     * GET /patient/wellness/export
     * Export all wellness data as a JSON summary.
     * Accepts optional ?from_date=YYYY-MM-DD&to_date=YYYY-MM-DD query params.
     * from_date is clamped to no earlier than the user's account creation date.
     * to_date is clamped to no later than today.
     */
    public function export(Request $request)
    {
        $user = $request->user();

        $accountMin = $user->created_at->toDateString();
        $todayMax = now()->toDateString();

        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        // Clamp from_date: must be >= account creation date and <= today
        if ($fromDate) {
            if ($fromDate < $accountMin) {
                $fromDate = $accountMin;
            }
            if ($fromDate > $todayMax) {
                $fromDate = $todayMax;
            }
        } else {
            $fromDate = $accountMin;
        }

        // Clamp to_date: must be >= from_date and <= today
        if ($toDate) {
            if ($toDate > $todayMax) {
                $toDate = $todayMax;
            }
            if ($toDate < $fromDate) {
                $toDate = $fromDate;
            }
        } else {
            $toDate = $todayMax;
        }

        $from = $fromDate.' 00:00:00';
        $to = $toDate.' 23:59:59';

        $exportData = [
            'exported_at' => now()->toISOString(),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'mood_logs' => MoodLog::where('user_id', $user->id)
                ->whereBetween('created_at', [$from, $to])
                ->orderBy('created_at')->get()->toArray(),
            'sleep_logs' => SleepLog::where('user_id', $user->id)
                ->whereBetween('created_at', [$from, $to])
                ->orderBy('created_at')->get()->toArray(),
            'journal_entries' => JournalEntry::where('user_id', $user->id)
                ->whereBetween('created_at', [$from, $to])
                ->orderBy('created_at')->get(['id', 'title', 'created_at'])->toArray(),
            'assessment_results' => UserAssessmentResult::where('user_id', $user->id)
                ->whereBetween('completed_at', [$from, $to])
                ->with('assessment:id,title')
                ->orderBy('completed_at')->get()->toArray(),
        ];

        Mail::to($user->email)->send(new WellnessDataExport(
            userName: $user->name,
            userEmail: $user->email,
            fromDate: $fromDate,
            toDate: $toDate,
            exportData: $exportData,
        ));

        return $this->sendResponse([
            'email' => $user->email,
        ], "Your wellness data has been sent to {$user->email}. Check your inbox — it may take a minute to arrive.");
    }

    // -------------------------------------------------------------------------

    private function computeScore($user): int
    {
        $since = now()->subDays(7);
        $score = 0;

        // Mood (max 40 pts) — avg mood on 1-10 scale → 0–40
        $avgMood = MoodLog::where('user_id', $user->id)->where('created_at', '>=', $since)->avg('mood_score') ?? 0;
        $score += min(40, (int) round(($avgMood / 10) * 40));

        // Sleep quality (max 30 pts) — avg quality on 1-10 → 0–30
        $avgSleepQuality = SleepLog::where('user_id', $user->id)->where('created_at', '>=', $since)->avg('quality_rating') ?? 0;
        $score += min(30, (int) round(($avgSleepQuality / 10) * 30));

        // Streak (max 20 pts) — capped at 14-day streak for full score
        $streak = Streak::where('user_id', $user->id)->first();
        $streakDays = $streak?->current_streak ?? 0;
        $score += min(20, (int) round(($streakDays / 14) * 20));

        // Journal activity (max 10 pts) — 1 entry = 2pts, capped at 5 entries
        $journalCount = JournalEntry::where('user_id', $user->id)->where('created_at', '>=', $since)->count();
        $score += min(10, $journalCount * 2);

        return min(100, $score);
    }

    private function scoreLabel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Thriving',
            $score >= 60 => 'Doing Well',
            $score >= 40 => 'Fair',
            $score >= 20 => 'Struggling',
            default => 'Needs Attention',
        };
    }
}
