<?php

namespace App\Repositories;

use App\Models\Therapist;

class TherapistRepository extends BaseRepository
{
    /**
     * Constructor
     */
    public function __construct(Therapist $model)
    {
        parent::__construct($model);
    }

    /**
     * Get active therapists
     */
    public function getActive()
    {
        return $this->model->where('status', 'active')->get();
    }

    /**
     * Get verified therapists
     */
    public function getVerified()
    {
        return $this->model->where('is_verified', true)->get();
    }

    /**
     * Get therapists by specialization
     */
    public function getBySpecialization($specialization)
    {
        return $this->model->where('specialization', $specialization)->get();
    }

    /**
     * Get top-rated therapists
     */
    public function getTopRated($limit = 10)
    {
        return $this->model
            ->withAvg('ratings', 'rating')
            ->orderByDesc('ratings_avg_rating')
            ->limit($limit)
            ->get();
    }

    /**
     * Get therapists with availability
     */
    public function getWithAvailability()
    {
        return $this->model
            ->where('status', 'active')
            ->where('is_verified', true)
            ->with('schedule', 'ratings')
            ->get();
    }

    /**
     * Search therapists
     */
    public function search($query)
    {
        return $this->model
            ->where('full_name', 'ilike', "%{$query}%")
            ->orWhere('specialization', 'ilike', "%{$query}%")
            ->orWhere('bio', 'ilike', "%{$query}%")
            ->get();
    }

    /**
     * Get therapist with stats
     */
    public function getWithStats($id)
    {
        $therapist = $this->find($id);

        if (! $therapist) {
            return null;
        }

        $therapist->stats = [
            'total_sessions' => $therapist->sessions()->count(),
            'completed_sessions' => $therapist->sessions()->where('status', 'completed')->count(),
            'average_rating' => $therapist->ratings()->avg('rating') ?? 0,
            'total_reviews' => $therapist->ratings()->count(),
            'total_earnings' => $therapist->sessions()
                ->where('status', 'completed')
                ->where('payment_status', 'completed')
                ->sum('session_fee'),
        ];

        return $therapist;
    }
}
