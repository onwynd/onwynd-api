<?php

namespace App\Repositories\Eloquent;

use App\Models\TherapistAvailability;
use App\Models\TherapistProfile;
use App\Repositories\Contracts\TherapistRepositoryInterface;

class TherapistRepository implements TherapistRepositoryInterface
{
    public function all()
    {
        return TherapistProfile::with('user')->where('is_verified', true)->get();
    }

    public function find($id)
    {
        return TherapistProfile::with(['user', 'availabilities'])->find($id);
    }

    public function getAvailableTherapists($date = null)
    {
        $query = TherapistProfile::where('is_verified', true);

        if ($date) {
            $query->whereHas('availabilities', function ($q) use ($date) {
                $q->where('date', $date)->where('is_booked', false);
            });
        }

        return $query->get();
    }

    public function getProfile($userId)
    {
        return TherapistProfile::where('user_id', $userId)->first();
    }

    public function updateAvailability($therapistId, array $availability)
    {
        // Implementation depends on structure, assuming batch update or single create
        return TherapistAvailability::create(array_merge(['therapist_id' => $therapistId], $availability));
    }
}
