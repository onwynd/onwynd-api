<?php

namespace App\Repositories;

use App\Models\TherapySession;

class TherapySessionRepository extends BaseRepository
{
    /**
     * Constructor
     */
    public function __construct(TherapySession $model)
    {
        parent::__construct($model);
    }

    /**
     * Get user's sessions
     */
    public function getUserSessions($userId, $status = null)
    {
        $query = $this->model->where('user_id', $userId)->with('therapist', 'payment');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('scheduled_date')->get();
    }

    /**
     * Get therapist's sessions
     */
    public function getTherapistSessions($therapistId, $status = null)
    {
        $query = $this->model->where('therapist_id', $therapistId)->with('user', 'payment');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('scheduled_date')->get();
    }

    /**
     * Get completed sessions for period
     */
    public function getCompletedByPeriod($therapistId, $startDate, $endDate)
    {
        return $this->model
            ->where('therapist_id', $therapistId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->get();
    }

    /**
     * Check if slot is available
     */
    public function isSlotAvailable($therapistId, $date, $time)
    {
        return ! $this->model
            ->where('therapist_id', $therapistId)
            ->where('scheduled_date', $date)
            ->where('scheduled_time', $time)
            ->where('status', '!=', 'cancelled')
            ->exists();
    }

    /**
     * Get sessions by date range
     */
    public function getByDateRange($therapistId, $startDate, $endDate)
    {
        return $this->model
            ->where('therapist_id', $therapistId)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get();
    }

    /**
     * Get upcoming sessions
     */
    public function getUpcoming($userId, $limit = 5)
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('scheduled_date', '>=', now()->format('Y-m-d'))
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Get session statistics
     */
    public function getStats($therapistId)
    {
        return [
            'total_sessions' => $this->model->where('therapist_id', $therapistId)->count(),
            'completed_sessions' => $this->model->where('therapist_id', $therapistId)->where('status', 'completed')->count(),
            'cancelled_sessions' => $this->model->where('therapist_id', $therapistId)->where('status', 'cancelled')->count(),
            'pending_sessions' => $this->model->where('therapist_id', $therapistId)->where('status', 'booked')->count(),
            'total_earnings' => $this->model
                ->where('therapist_id', $therapistId)
                ->where('status', 'completed')
                ->where('payment_status', 'completed')
                ->sum('session_fee'),
        ];
    }
}
