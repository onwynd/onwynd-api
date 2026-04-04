<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\Payment\Subscription as PaymentSubscription;
use App\Models\SleepLog;
use App\Models\SleepSchedule;
use App\Models\Subscription as LegacySubscription;
use App\Services\OnwyndScoreService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SleepController extends BaseController
{
    protected $scoreService;

    public function __construct(OnwyndScoreService $scoreService)
    {
        $this->scoreService = $scoreService;
    }

    public function insights(Request $request)
    {
        if (! $this->hasActiveSubscription($request)) {
            return $this->sendError('Premium feature. Upgrade to access sleep insights.', [
                'upsell' => [
                    'message' => 'Sleep tracking is a premium feature. Unlock with ₦2,999/mo',
                    'subscribe_url' => '/subscription/upgrade',
                ],
            ], 403);
        }
        $insights = [
            'quality_trend' => 'improving',
            'average_duration' => '7h 30m',
            'suggestions' => [
                'Try to go to bed 30 mins earlier.',
                'Avoid caffeine after 4 PM.',
            ],
        ];

        return $this->sendResponse($insights, 'Sleep insights retrieved.');
    }

    public function index(Request $request)
    {
        if (! $this->hasActiveSubscription($request)) {
            return $this->sendError('Premium feature. Upgrade to access sleep tracking.', [
                'upsell' => [
                    'message' => 'Sleep tracking is a premium feature. Unlock with ₦2,999/mo',
                    'subscribe_url' => '/subscription/upgrade',
                ],
            ], 403);
        }
        $logs = SleepLog::where('user_id', $request->user()->id)
            ->orderBy('start_time', 'desc')
            ->limit(30)
            ->get();

        $schedule = SleepSchedule::where('user_id', $request->user()->id)->first();

        return $this->sendResponse([
            'logs' => $logs,
            'schedule' => $schedule,
        ], 'Sleep data retrieved successfully.');
    }

    public function store(Request $request)
    {
        if (! $this->hasActiveSubscription($request)) {
            return $this->sendError('Premium feature. Upgrade to log sleep.', [
                'upsell' => [
                    'message' => 'Sleep tracking is a premium feature. Unlock with ₦2,999/mo',
                    'subscribe_url' => '/subscription/upgrade',
                ],
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'quality_rating' => 'nullable|integer|min:0|max:100',
            'interruptions' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $durationMinutes = Carbon::parse($request->start_time)->diffInMinutes(Carbon::parse($request->end_time));

        $log = SleepLog::create([
            'user_id' => $request->user()->id,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'duration_minutes' => $durationMinutes,
            'quality_rating' => $request->quality_rating,
            'interruptions' => $request->interruptions ?? 0,
            'notes' => $request->notes,
            'source' => 'manual',
        ]);

        // Update Onwynd Score
        $this->scoreService->updateScore($request->user());

        return $this->sendResponse($log, 'Sleep log created successfully.');
    }

    public function show($id)
    {
        if (! auth()->user()) {
            return $this->sendError('Unauthenticated.', [], 401);
        }
        if (! $this->hasActiveSubscription(request())) {
            return $this->sendError('Premium feature. Upgrade to access sleep logs.', [
                'upsell' => [
                    'message' => 'Sleep tracking is a premium feature. Unlock with ₦2,999/mo',
                    'subscribe_url' => '/subscription/upgrade',
                ],
            ], 403);
        }
        $log = SleepLog::where('user_id', auth()->id())->find($id);

        if (! $log) {
            return $this->sendError('Sleep log not found.');
        }

        return $this->sendResponse($log, 'Sleep log retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        if (! $this->hasActiveSubscription($request)) {
            return $this->sendError('Premium feature. Upgrade to update sleep logs.', [
                'upsell' => [
                    'message' => 'Sleep tracking is a premium feature. Unlock with ₦2,999/mo',
                    'subscribe_url' => '/subscription/upgrade',
                ],
            ], 403);
        }
        $log = SleepLog::where('user_id', auth()->id())->find($id);

        if (! $log) {
            return $this->sendError('Sleep log not found.');
        }

        $log->update($request->all());

        if ($request->has('start_time') || $request->has('end_time')) {
            $log->duration_minutes = Carbon::parse($log->start_time)->diffInMinutes(Carbon::parse($log->end_time));
            $log->save();
        }

        return $this->sendResponse($log, 'Sleep log updated successfully.');
    }

    public function destroy($id)
    {
        if (! auth()->user()) {
            return $this->sendError('Unauthenticated.', [], 401);
        }
        if (! $this->hasActiveSubscription(request())) {
            return $this->sendError('Premium feature. Upgrade to delete sleep logs.', [
                'upsell' => [
                    'message' => 'Sleep tracking is a premium feature. Unlock with ₦2,999/mo',
                    'subscribe_url' => '/subscription/upgrade',
                ],
            ], 403);
        }
        $log = SleepLog::where('user_id', auth()->id())->find($id);

        if (! $log) {
            return $this->sendError('Sleep log not found.');
        }

        $log->delete();

        return $this->sendResponse([], 'Sleep log deleted successfully.');
    }

    public function stats(Request $request)
    {
        if (! $this->hasActiveSubscription($request)) {
            return $this->sendError('Premium feature. Upgrade to access sleep stats.', [
                'upsell' => [
                    'message' => 'Sleep tracking is a premium feature. Unlock with ₦2,999/mo',
                    'subscribe_url' => '/subscription/upgrade',
                ],
            ], 403);
        }

        $userId = $request->user()->id;
        $period = $request->input('period', 'week');
        $days = match ($period) {
            'month' => 30,
            'year' => 365,
            default => 7, // week
        };

        $logs = SleepLog::where('user_id', $userId)
            ->where('start_time', '>=', now()->subDays($days))
            ->get();

        $avgDuration = $logs->avg('duration_minutes') ?? 0;
        $avgQuality = $logs->avg('quality_rating') ?? 0;

        return $this->sendResponse([
            'period' => $period,
            'total_logs' => $logs->count(),
            'average_duration_hours' => round($avgDuration / 60, 2),
            'average_quality' => round($avgQuality, 1),
            'best_night' => $logs->sortByDesc('quality_rating')->first()?->only(['start_time', 'duration_minutes', 'quality_rating']),
        ], 'Sleep stats retrieved.');
    }

    public function trends(Request $request)
    {
        if (! $this->hasActiveSubscription($request)) {
            return $this->sendError('Premium feature. Upgrade to access sleep trends.', [
                'upsell' => [
                    'message' => 'Sleep trends is a premium feature. Unlock with ₦2,999/mo',
                    'subscribe_url' => '/subscription/upgrade',
                ],
            ], 403);
        }

        // Accept from_date/to_date (from frontend) or fallback to days
        if ($request->has('from_date') || $request->has('to_date')) {
            $from = $request->input('from_date', now()->subDays(30)->toDateString());
            $to = $request->input('to_date', now()->toDateString());
            $logs = SleepLog::where('user_id', $request->user()->id)
                ->whereDate('start_time', '>=', $from)
                ->whereDate('start_time', '<=', $to)
                ->orderBy('start_time', 'asc')
                ->get();
        } else {
            $days = (int) $request->input('days', 30);
            $logs = SleepLog::where('user_id', $request->user()->id)
                ->where('start_time', '>=', now()->subDays($days))
                ->orderBy('start_time', 'asc')
                ->get();
        }

        $dates = $logs->map(fn ($l) => Carbon::parse($l->start_time)->toDateString())->toArray();
        $durations = $logs->map(fn ($l) => round($l->duration_minutes / 60, 2))->toArray();
        $qualities = $logs->map(fn ($l) => $l->quality_rating)->toArray();

        $avgDuration = count($durations) ? round(array_sum($durations) / count($durations), 2) : 0;
        $avgQuality = count($qualities) ? round(array_sum(array_filter($qualities)) / max(count(array_filter($qualities)), 1), 1) : 0;

        return $this->sendResponse([
            'dates' => $dates,
            'durations_hours' => $durations,
            'quality_ratings' => $qualities,
            'averages' => [
                'duration_hours' => $avgDuration,
                'quality_rating' => $avgQuality,
            ],
        ], 'Sleep trends retrieved.');
    }

    public function updateSchedule(Request $request)
    {
        if (! $this->hasActiveSubscription($request)) {
            return $this->sendError('Premium feature. Upgrade to set sleep schedule.', [
                'upsell' => [
                    'message' => 'Sleep tracking is a premium feature. Unlock with ₦2,999/mo',
                    'subscribe_url' => '/subscription/upgrade',
                ],
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'target_bedtime' => 'required',
            'target_wake_time' => 'required',
            'days_of_week' => 'array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $schedule = SleepSchedule::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'target_bedtime' => $request->target_bedtime,
                'target_wake_time' => $request->target_wake_time,
                'reminder_enabled' => $request->reminder_enabled ?? true,
                'reminder_minutes_before' => $request->reminder_minutes_before ?? 30,
                'days_of_week' => $request->days_of_week,
            ]
        );

        return $this->sendResponse($schedule, 'Sleep schedule updated successfully.');
    }

    private function hasActiveSubscription(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }
        try {
            $sub = PaymentSubscription::where('user_id', $user->id)->active()->latest()->first();
            if ($sub) {
                return true;
            }
        } catch (\Throwable $e) {
        }
        try {
            $legacy = LegacySubscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('current_period_start', '<=', now())
                ->where('current_period_end', '>=', now())
                ->latest('current_period_end')
                ->first();
            if ($legacy) {
                return true;
            }
        } catch (\Throwable $e) {
        }

        return false;
    }
}
