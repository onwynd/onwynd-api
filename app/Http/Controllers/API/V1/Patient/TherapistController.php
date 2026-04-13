<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\TherapistAvailability;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TherapistController extends BaseController
{
    public function index(Request $request)
    {
        $query = User::whereHas('role', function ($q) {
            $q->where('slug', 'therapist');
        })->with('therapistProfile');

        if ($request->has('specialization')) {
            $term = strtolower($request->specialization);
            $query->whereHas('therapistProfile', function ($q) use ($term) {
                $q->whereRaw('LOWER(CAST(specializations AS CHAR)) LIKE ?', ["%{$term}%"]);
            });
        }

        if ($request->has('language')) {
            $query->whereHas('therapistProfile', function ($q) use ($request) {
                $q->where('languages', 'like', '%'.$request->language.'%');
            });
        }

        if ($request->has('name')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%'.$request->name.'%')
                    ->orWhere('last_name', 'like', '%'.$request->name.'%');
            });
        }

        if ($request->has('min_rate')) {
            $query->whereHas('therapistProfile', function ($q) use ($request) {
                $q->where('hourly_rate', '>=', $request->min_rate);
            });
        }

        if ($request->has('max_rate')) {
            $query->whereHas('therapistProfile', function ($q) use ($request) {
                $q->where('hourly_rate', '<=', $request->max_rate);
            });
        }

        if ($request->has('min_rating')) {
            $query->whereHas('therapistProfile', function ($q) use ($request) {
                $q->where('rating_average', '>=', $request->min_rating);
            });
        }

        if ($request->boolean('is_accepting_clients')) {
            $query->whereHas('therapistProfile', function ($q) {
                $q->where('is_accepting_clients', true);
            });
        }

        $perPage = $request->get('per_page', 15);
        $therapists = $query->paginate($perPage);

        // Append next available slot to each therapist
        $therapists->getCollection()->transform(function ($therapist) {
            $therapist->next_available_slot = $this->calculateNextAvailableSlot($therapist->id);

            return $therapist;
        });

        return $this->sendResponse($therapists, 'Therapists retrieved successfully.');
    }

    public function show($id)
    {
        $therapist = User::whereHas('role', function ($q) {
            $q->where('slug', 'therapist');
        })->with('therapistProfile')
            ->where(function ($q) use ($id) {
                $q->where('uuid', $id)->orWhere('id', $id);
            })->first();

        if (! $therapist) {
            return $this->sendError('Therapist not found.');
        }

        $profile = $therapist->therapistProfile;

        $data = [
            'id'                  => $profile?->id ?? $therapist->id,
            'uuid'                => $therapist->uuid,
            'user'                => [
                'id'            => $therapist->id,
                'uuid'          => $therapist->uuid,
                'first_name'    => $therapist->first_name,
                'last_name'     => $therapist->last_name,
                'email'         => $therapist->email,
                'profile_photo' => $therapist->profile_photo_url,
            ],
            'license_number'      => $profile?->license_number,
            'specializations'     => $profile?->specializations ?? [],
            'qualifications'      => $profile?->qualifications ?? [],
            'languages'           => $profile?->languages ?? [],
            'years_experience'    => $profile?->experience_years ?? 0,
            'session_rate'        => $profile?->hourly_rate ?? 0,
            'currency'            => $profile?->currency ?? 'NGN',
            'bio_short'           => $profile?->bio,
            'bio_long'            => $profile?->bio ?? '',
            'video_intro_url'     => $profile?->video_intro_url,
            'is_verified'         => $profile?->is_verified ?? false,
            'verification_status' => $profile?->status ?? 'pending',
            'is_accepting_clients'=> $profile?->is_accepting_clients ?? false,
            'rating_average'      => $profile?->rating_average ?? 0,
            'total_sessions'      => $profile?->total_sessions ?? 0,
            'total_reviews'       => 0,
            'availability'        => [],
            'next_available_slot' => $this->calculateNextAvailableSlot($therapist->id),
            'created_at'          => $therapist->created_at,
            'updated_at'          => $therapist->updated_at,
        ];

        return $this->sendResponse($data, 'Therapist details retrieved successfully.');
    }

    protected function calculateNextAvailableSlot($therapistId)
    {
        $now = now();
        $startDate = $now->copy()->addHours(2); // Minimum 2 hours notice

        // Check for next 7 days
        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dayNum = (int) $date->format('w');
            $dateStr = $date->format('Y-m-d');

            $windows = TherapistAvailability::where('therapist_id', $therapistId)
                ->where('is_available', true)
                ->where(function ($q) use ($dateStr, $dayNum) {
                    $q->where(function ($r) use ($dayNum) {
                        $r->where('is_recurring', true)
                            ->where('day_of_week', $dayNum);
                    })->orWhere(function ($s) use ($dateStr) {
                        $s->where('is_recurring', false)
                            ->where('specific_date', $dateStr);
                    });
                })
                ->orderBy('start_time')
                ->get();

            $bookedTimes = \App\Models\TherapySession::where('therapist_id', $therapistId)
                ->whereDate('scheduled_at', $dateStr)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->pluck('scheduled_at')
                ->map(fn ($dt) => date('H:i', strtotime($dt)))
                ->toArray();

            foreach ($windows as $window) {
                $startTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $dateStr.' '.$window->start_time);
                $endTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $dateStr.' '.$window->end_time);

                // Check if this slot is in the past
                if ($startTime->isBefore($startDate)) {
                    continue;
                }

                // Check if this slot is already booked
                if (! in_array($startTime->format('H:i'), $bookedTimes)) {
                    return $startTime->toIso8601String();
                }
            }
        }

        return null;
    }

    public function specializations()
    {
        $specializations = TherapistProfile::whereHas('user', function ($q) {
            $q->whereHas('role', function ($r) {
                $r->where('slug', 'therapist');
            });
        })
            ->where('is_verified', true)
            ->whereNotNull('specializations')
            ->pluck('specializations')
            ->flatten()
            ->filter()
            ->unique()
            ->values();

        return $this->sendResponse($specializations, 'Specializations retrieved successfully.');
    }

    public function languages()
    {
        $languages = TherapistProfile::whereHas('user', function ($q) {
            $q->whereHas('role', function ($r) {
                $r->where('slug', 'therapist');
            });
        })
            ->where('is_verified', true)
            ->whereNotNull('languages')
            ->pluck('languages')
            ->flatten()
            ->filter()
            ->unique()
            ->values();

        return $this->sendResponse($languages, 'Languages retrieved successfully.');
    }

    public function availability(Request $request, $id)
    {
        $therapist = User::whereHas('role', function ($q) {
            $q->where('slug', 'therapist');
        })->where(function ($q) use ($id) {
            $q->where('uuid', $id)->orWhere('id', $id);
        })->first();

        if (! $therapist) {
            return $this->sendError('Therapist not found.');
        }

        if ($request->has('date')) {
            $date = $request->date;
            // day_of_week is stored as integer: 0=Sunday … 6=Saturday
            $dayNum = (int) date('w', strtotime($date));

            $windows = TherapistAvailability::where('therapist_id', $therapist->id)
                ->where('is_available', true)
                ->where(function ($q) use ($date, $dayNum) {
                    $q->where(function ($r) use ($dayNum) {
                        $r->where('is_recurring', true)
                            ->where('day_of_week', $dayNum);
                    })->orWhere(function ($s) use ($date) {
                        $s->where('is_recurring', false)
                            ->whereDate('specific_date', $date);
                    });
                })
                ->orderBy('start_time')
                ->get(['start_time', 'end_time'])
                ->map(fn ($w) => [
                    'start_time' => $w->start_time,
                    'end_time' => $w->end_time,
                ])
                ->values()
                ->toArray();

            // Slots already booked on this date (exclude cancelled / no-shows)
            $bookedTimes = \App\Models\TherapySession::where('therapist_id', $therapist->id)
                ->whereDate('scheduled_at', $date)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->pluck('scheduled_at')
                ->map(fn ($dt) => date('H:i', strtotime($dt)))
                ->values()
                ->toArray();

            return $this->sendResponse([
                'windows' => $windows,
                'booked_times' => $bookedTimes,
            ], 'Availability retrieved successfully.');
        }

        // No date → weekday hints for the calendar (0=Sun … 6=Sat).
        // Include recurring days plus weekdays of upcoming one-off (specific_date) rows so
        // seeded specific-date availability still shows strikethrough / hints correctly.
        $recurringDays = TherapistAvailability::where('therapist_id', $therapist->id)
            ->where('is_available', true)
            ->where('is_recurring', true)
            ->pluck('day_of_week')
            ->all();

        $specificWeekdays = TherapistAvailability::where('therapist_id', $therapist->id)
            ->where('is_available', true)
            ->where('is_recurring', false)
            ->whereNotNull('specific_date')
            ->whereDate('specific_date', '>=', now()->toDateString())
            ->pluck('specific_date')
            ->map(fn ($d) => (int) date('w', strtotime((string) $d)))
            ->all();

        $availableDays = collect($recurringDays)
            ->merge($specificWeekdays)
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        return $this->sendResponse([
            'available_days' => $availableDays,
        ], 'Schedule retrieved successfully.');
    }

    /**
     * GET /api/v1/therapists/available-now
     * Returns therapists who are online, not in an active session, and have an
     * availability window covering the current time. Used by the booking page
     * to surface the "X available right now — start immediately or schedule later" banner.
     */
    public function availableNow()
    {
        $now         = now();
        $currentDay  = (int) $now->format('w');      // 0 = Sunday … 6 = Saturday
        $currentTime = $now->format('H:i:s');

        $therapists = User::whereHas('role', fn ($q) => $q->where('slug', 'therapist'))
            ->where('is_online', true)
            ->where('is_active', true)
            ->whereHas('therapistProfile', fn ($q) =>
                $q->where('is_accepting_clients', true)
                  ->where('is_verified', true)
                  // Only approved therapists appear as "Available Now"
                  ->where('verification_status', 'approved')
            )
            // Not currently in an active session
            ->whereNotExists(fn ($q) =>
                $q->select(DB::raw(1))
                  ->from('therapy_sessions')
                  ->whereColumn('therapist_id', 'users.id')
                  ->where('status', 'in_progress')
            )
            // Not in a session that starts within the next 3 hours (buffer: they need prep time)
            ->whereNotExists(fn ($q) =>
                $q->select(DB::raw(1))
                  ->from('therapy_sessions')
                  ->whereColumn('therapist_id', 'users.id')
                  ->whereIn('status', ['scheduled', 'confirmed'])
                  ->whereBetween('scheduled_at', [$now, $now->copy()->addHours(3)])
            )
            // Has an availability window that covers right now
            ->whereExists(fn ($q) =>
                $q->select(DB::raw(1))
                  ->from('therapist_availabilities')
                  ->whereColumn('therapist_id', 'users.id')
                  ->where('is_available', true)
                  ->where('is_recurring', true)
                  ->where('day_of_week', $currentDay)
                  ->where('start_time', '<=', $currentTime)
                  ->where('end_time',   '>',  $currentTime)
            )
            ->select('id', 'uuid', 'first_name', 'last_name', 'profile_photo')
            ->get();

        return $this->sendResponse([
            'count'            => $therapists->count(),
            'therapist_uuids'  => $therapists->pluck('uuid')->values()->toArray(),
        ], 'Available-now therapists retrieved.');
    }

    public function reviews($id)
    {
        $therapist = User::whereHas('role', function ($q) {
            $q->where('slug', 'therapist');
        })->where(function ($q) use ($id) {
            $q->where('uuid', $id)->orWhere('id', $id);
        })->first();

        if (! $therapist) {
            return $this->sendError('Therapist not found.');
        }

        // Return rating summary from therapist profile
        $profile = TherapistProfile::where('user_id', $therapist->id)->first();

        $reviewData = [
            'rating_average' => $profile ? $profile->rating_average : 0,
            'total_sessions' => $profile ? $profile->total_sessions : 0,
            'reviews' => [],
        ];

        return $this->sendResponse($reviewData, 'Reviews retrieved successfully.');
    }
}
