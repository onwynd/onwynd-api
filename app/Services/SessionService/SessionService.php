<?php

namespace App\Services\SessionService;

use App\Models\Therapist;
use App\Models\TherapySession;
use App\Repositories\TherapySessionRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Session Service
 * Handles session-related business logic
 */
class SessionService
{
    /**
     * @var TherapySessionRepository
     */
    private $sessionRepository;

    /**
     * Constructor
     */
    public function __construct(TherapySessionRepository $sessionRepository)
    {
        $this->sessionRepository = $sessionRepository;
    }

    /**
     * Check therapist availability
     */
    public function isTherapistAvailable(Therapist $therapist, $date, $time)
    {
        try {
            Log::info('Checking therapist availability', [
                'therapist_id' => $therapist->id,
                'date' => $date,
                'time' => $time,
            ]);

            return $this->sessionRepository->isSlotAvailable($therapist->id, $date, $time);

        } catch (\Exception $e) {
            Log::error('Therapist availability check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Calculate session fee
     */
    public function calculateSessionFee(Therapist $therapist, $durationMinutes = 60)
    {
        $hourlyRate = $therapist->hourly_rate;

        return ($hourlyRate / 60) * $durationMinutes;
    }

    /**
     * Get available slots for therapist on specific date
     */
    public function getAvailableSlots(Therapist $therapist, $date)
    {
        try {
            $dayOfWeek = Carbon::parse($date)->dayOfWeek;
            $schedule = $therapist->schedule()
                ->where('day_of_week', $dayOfWeek)
                ->first();

            if (! $schedule) {
                return [];
            }

            $slots = [];
            $startTime = Carbon::createFromFormat('H:i', $schedule->start_time);
            $endTime = Carbon::createFromFormat('H:i', $schedule->end_time);

            while ($startTime < $endTime) {
                $slotTime = $startTime->format('H:i');

                if ($this->sessionRepository->isSlotAvailable($therapist->id, $date, $slotTime)) {
                    $slots[] = [
                        'time' => $slotTime,
                        'display' => $startTime->format('g:i A'),
                        'available' => true,
                    ];
                }

                $startTime->addHour();
            }

            return $slots;

        } catch (\Exception $e) {
            Log::error('Get available slots failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Get upcoming sessions for user
     */
    public function getUpcomingSessions($userId, $limit = 5)
    {
        return $this->sessionRepository->getUpcoming($userId, $limit);
    }

    /**
     * Get session statistics
     */
    public function getSessionStats(Therapist $therapist)
    {
        return $this->sessionRepository->getStats($therapist->id);
    }

    /**
     * Auto-complete expired sessions
     */
    public function autoCompleteExpiredSessions()
    {
        try {
            $sessions = TherapySession::where('status', 'scheduled')
                ->where('scheduled_date', '<', now()->format('Y-m-d'))
                ->orWhere(function ($query) {
                    $query->where('scheduled_date', now()->format('Y-m-d'))
                        ->where('scheduled_time', '<', now()->format('H:i'));
                })
                ->get();

            foreach ($sessions as $session) {
                $session->update(['status' => 'completed']);
            }

            Log::info('Auto-completed expired sessions', ['count' => $sessions->count()]);

        } catch (\Exception $e) {
            Log::error('Auto-complete sessions failed', ['error' => $e->getMessage()]);
        }
    }
}
