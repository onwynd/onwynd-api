<?php

namespace App\Repositories\Eloquent;

use App\Models\Payment;
use App\Models\SessionNote;
use App\Models\SupportTicket;
use App\Models\Therapist;
use App\Models\TherapistAvailability;
use App\Models\TherapySession;
use App\Models\User;
use App\Repositories\Contracts\TherapyRepositoryInterface;
use Carbon\Carbon;

class TherapyEloquentRepository extends BaseRepository implements TherapyRepositoryInterface
{
    public function __construct(TherapySession $model)
    {
        parent::__construct($model);
    }

    public function findUpcomingSessions(string $userId, int $limit = 5): mixed
    {
        return $this->model->where(function ($query) use ($userId) {
            $query->where('patient_id', $userId)
                ->orWhere('therapist_id', $userId);
        })
            ->where('scheduled_at', '>=', now())
            ->where('status', 'scheduled')
            ->orderBy('scheduled_at', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getSessionHistory(string $userId, ?int $limit = null): mixed
    {
        $query = $this->model->where(function ($query) use ($userId) {
            $query->where('patient_id', $userId)
                ->orWhere('therapist_id', $userId);
        })
            ->where('status', 'completed')
            ->orderBy('scheduled_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function checkAvailability(int $therapistId, string $date, string $time, int $duration = 60, ?int $excludeSessionId = null): bool
    {
        $requestedStart = Carbon::parse("$date $time");
        $requestedEnd = $requestedStart->copy()->addMinutes($duration);

        // 1. Check Working Hours (TherapistAvailability)
        $dayOfWeek = $requestedStart->dayOfWeek; // 0 (Sunday) - 6 (Saturday)

        $isWorking = TherapistAvailability::where('therapist_id', $therapistId)
            ->where('is_available', true)
            ->where(function ($q) use ($requestedStart, $dayOfWeek) {
                // Check specific date override
                $q->where('specific_date', $requestedStart->toDateString())
                    // OR recurring schedule for this day
                    ->orWhere(function ($subQ) use ($dayOfWeek) {
                        $subQ->where('is_recurring', true)
                            ->where('day_of_week', $dayOfWeek)
                            ->whereNull('specific_date');
                    });
            })
            ->where(function ($q) use ($requestedStart, $requestedEnd) {
                // Time range check: start_time <= requestedStart AND end_time >= requestedEnd
                $q->whereTime('start_time', '<=', $requestedStart->toTimeString())
                    ->whereTime('end_time', '>=', $requestedEnd->toTimeString());
            })
            ->exists();

        // If no availability slot covers this time, they are not available
        if (! $isWorking) {
            return false;
        }

        // 2. Check Existing Sessions (Double Booking)
        // Fetch sessions for the day to check overlaps
        $query = $this->model->where('therapist_id', $therapistId)
            ->whereDate('scheduled_at', $requestedStart->toDateString())
            ->where('status', '!=', 'cancelled');

        if ($excludeSessionId) {
            $query->where('id', '!=', $excludeSessionId);
        }

        $sessions = $query->get();

        foreach ($sessions as $session) {
            $sessionStart = Carbon::parse($session->scheduled_at);
            // Use session duration or default to 60 if not set
            $sessionDuration = $session->duration_minutes ?? 60;
            $sessionEnd = $sessionStart->copy()->addMinutes($sessionDuration);

            // Overlap condition: StartA < EndB && EndA > StartB
            if ($requestedStart->lt($sessionEnd) && $requestedEnd->gt($sessionStart)) {
                return false;
            }
        }

        return true;
    }

    public function getTherapistSessions(int $therapistId, array $filters = []): mixed
    {
        $query = $this->model->where('therapist_id', $therapistId)
            ->with(['patient:id,first_name,last_name,profile_photo']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date'])) {
            $query->whereDate('scheduled_at', $filters['date']);
        }

        if (isset($filters['type'])) {
            if ($filters['type'] === 'upcoming') {
                $query->where('scheduled_at', '>', now())->orderBy('scheduled_at', 'asc');
            } elseif ($filters['type'] === 'past') {
                $query->where('ended_at', '<', now())->orderBy('ended_at', 'desc');
            }
        } else {
            $query->orderBy('scheduled_at', 'desc');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getPatientSessions(int $patientId, array $filters = []): mixed
    {
        $query = $this->model->where('patient_id', $patientId)
            ->with(['therapist:id,first_name,last_name,profile_photo']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            if ($filters['type'] === 'upcoming') {
                $query->where('scheduled_at', '>', now())->orderBy('scheduled_at', 'asc');
            } elseif ($filters['type'] === 'past') {
                $query->where('ended_at', '<', now())->orderBy('ended_at', 'desc');
            }
        } else {
            $query->orderBy('scheduled_at', 'desc');
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function getAllSessions(array $filters = []): mixed
    {
        $query = $this->model->with(['patient:id,first_name,last_name,email', 'therapist:id,first_name,last_name,email']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date'])) {
            $query->whereDate('scheduled_at', $filters['date']);
        }

        return $query->orderBy('scheduled_at', 'desc')->paginate($filters['per_page'] ?? 20);
    }

    public function getTherapistStats(int $therapistId): array
    {
        return [
            'upcoming_sessions_count' => $this->model->where('therapist_id', $therapistId)
                ->where('scheduled_at', '>', now())
                ->where('status', 'scheduled')
                ->count(),
            'total_patients' => $this->model->where('therapist_id', $therapistId)
                ->distinct('patient_id')
                ->count('patient_id'),
            'today_sessions' => $this->model->where('therapist_id', $therapistId)
                ->whereDate('scheduled_at', today())
                ->with(['patient:id,first_name,last_name,profile_photo'])
                ->orderBy('scheduled_at', 'asc')
                ->get(),
        ];
    }

    public function hasRelationship(int $therapistId, int $patientId): bool
    {
        return $this->model->where('therapist_id', $therapistId)
            ->where('patient_id', $patientId)
            ->exists();
    }

    public function getSharedSessions(int $therapistId, int $patientId): mixed
    {
        return $this->model->where('therapist_id', $therapistId)
            ->where('patient_id', $patientId)
            ->with('sessionNote')
            ->orderBy('scheduled_at', 'desc')
            ->get();
    }

    public function getPatientStats(int $patientId): array
    {
        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);

        // Mood streak: count consecutive days with a mood log up to today
        $moodStreak = 0;
        $checkDate = $now->copy()->startOfDay();
        for ($i = 0; $i < 365; $i++) {
            $hasLog = \App\Models\MoodLog::where('user_id', $patientId)
                ->whereDate('created_at', $checkDate)
                ->exists();
            if ($hasLog) {
                $moodStreak++;
                $checkDate->subDay();
            } else {
                break;
            }
        }

        return [
            'completed_sessions' => $this->model->where('patient_id', $patientId)->where('status', 'completed')->count(),
            'upcoming_sessions' => $this->model->where('patient_id', $patientId)
                ->where('status', 'confirmed')
                ->where('scheduled_at', '>', $now)
                ->count(),
            'mood_streak' => $moodStreak,
            'unread_messages' => \App\Models\Chat::where('to_user_id', $patientId)
                ->whereNull('read_at')
                ->count(),
            'active_assessments' => \App\Models\Assessment::where('is_active', true)->count(),
        ];
    }

    public function getAdminStats(): array
    {
        return [
            'total_users' => User::count(),
            'total_patients' => User::whereHas('role', function ($q) {
                $q->where('slug', 'patient');
            })->count(),
            'total_therapists' => User::whereHas('role', function ($q) {
                $q->where('slug', 'therapist');
            })->count(),
            'total_sessions' => $this->model->count(),
            'sessions_this_month' => $this->model->whereMonth('created_at', now()->month)->count(),
            'total_revenue' => Payment::where('status', 'successful')->sum('amount'),
            'revenue_this_month' => Payment::where('status', 'successful')->whereMonth('created_at', now()->month)->sum('amount'),
            'open_tickets' => SupportTicket::where('status', 'open')->count(),
            'recent_users' => User::with('role')->orderBy('created_at', 'desc')->take(5)->get(),
        ];
    }

    public function updateSessionNote(int $sessionId, array $data): mixed
    {
        return SessionNote::updateOrCreate(
            ['session_id' => $sessionId],
            $data
        );
    }

    public function getPatientIds(int $therapistId): array
    {
        return $this->model->where('therapist_id', $therapistId)
            ->distinct()
            ->pluck('patient_id')
            ->toArray();
    }

    public function getTherapistPatients(int $therapistId): mixed
    {
        // Get all sessions for this therapist with patient data
        $sessions = $this->model->where('therapist_id', $therapistId)
            ->with('patient.profile')
            ->get();

        // Group by patient_id and map to desired structure
        return $sessions->groupBy('patient_id')->map(function ($patientSessions) {
            $firstSession = $patientSessions->first();
            $patient = $firstSession->patient;

            return [
                'user_id' => $patient->id,
                'name' => $patient->full_name ?? $patient->first_name.' '.$patient->last_name, // Adjust based on User model accessor
                'email' => $patient->email,
                'sessions_completed' => $patientSessions->where('status', 'completed')->count(),
                'last_session' => $patientSessions->sortByDesc('ended_at')->first()?->ended_at,
                'primary_concern' => $patient->profile?->primary_concern ?? null,
            ];
        })->values();
    }

    public function getTherapistEarnings(int $therapistId): array
    {
        $sessions = $this->model->where('therapist_id', $therapistId)
            ->where('status', 'completed')
            ->whereMonth('ended_at', now()->month)
            ->get();

        $earningsThisMonth = $sessions->sum('session_rate');

        $breakdown = [
            'by_session_type' => $sessions->groupBy('session_type')->map(fn ($g) => $g->count()),
            'by_week' => $sessions->groupBy(fn ($s) => $s->ended_at->weekOfYear)->map(fn ($g) => $g->count()),
            'total_sessions_month' => $sessions->count(),
            'average_per_session' => $sessions->count() > 0
                ? round($sessions->sum('session_rate') / $sessions->count(), 2)
                : 0,
        ];

        // Projection logic
        $daysInMonth = now()->daysInMonth;
        $daysElapsed = now()->day;

        $projectedMonthly = $daysElapsed > 0
            ? round(($earningsThisMonth / $daysElapsed) * $daysInMonth, 2)
            : 0;

        return [
            'earnings_this_month' => $earningsThisMonth,
            'breakdown' => $breakdown,
            'projections' => $projectedMonthly, // Returning value directly, not array
        ];
    }

    public function getAvailableTherapists(array $filters): mixed
    {
        $date = $filters['date'] ?? null;
        $specialization = $filters['specialization'] ?? null;

        $query = Therapist::where('status', 'active')
            ->where('is_verified', true)
            ->with(['schedule', 'ratings']);

        if ($specialization) {
            $query->whereJsonContains('specializations', $specialization);
        }

        return $query->get()->map(function ($therapist) use ($date) {
            return [
                'id' => $therapist->id,
                'name' => $therapist->full_name,
                'specialization' => $therapist->specialization,
                'qualification' => $therapist->qualification,
                'experience_years' => $therapist->years_of_experience,
                'bio' => $therapist->bio,
                'hourly_rate' => $therapist->hourly_rate,
                'currency' => 'NGN',
                'rating' => $therapist->ratings()->avg('rating') ?? 0,
                'total_reviews' => $therapist->ratings()->count(),
                'available_slots' => $this->getAvailableSlots($therapist, $date),
                'avatar_url' => $therapist->avatar_url,
            ];
        });
    }

    public function findTherapist(int $id): ?object
    {
        return Therapist::find($id);
    }

    private function getAvailableSlots(Therapist $therapist, ?string $date = null): array
    {
        if (! $date) {
            $date = now()->format('Y-m-d');
        }

        // Get therapist's working hours
        $dayOfWeek = Carbon::parse($date)->dayOfWeek;
        $schedule = $therapist->schedule()
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (! $schedule) {
            return []; // Therapist doesn't work this day
        }

        $slots = [];
        $startTime = Carbon::createFromFormat('H:i', $schedule->start_time);
        $endTime = Carbon::createFromFormat('H:i', $schedule->end_time);

        // Generate 1-hour slots
        while ($startTime < $endTime) {
            $slotTime = $startTime->format('H:i');

            // Check if slot is already booked
            $isBooked = ! $this->checkAvailability(
                $therapist->id,
                $date,
                $slotTime
            );

            if (! $isBooked) {
                $slots[] = [
                    'time' => $slotTime,
                    'display' => $startTime->format('g:i A'),
                    'available' => true,
                ];
            }

            $startTime->addHour();
        }

        return $slots;
    }
}
