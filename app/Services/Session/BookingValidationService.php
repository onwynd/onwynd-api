<?php

namespace App\Services\Session;

use App\Enums\TherapySessionStatus;
use App\Models\TherapySession;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BookingValidationService
{
    /**
     * Validate anonymous booking — prevent quota bypass via device fingerprint.
     * Throws a ValidationException (returns HTTP 422) if a recent anonymous
     * booking already exists from the same device fingerprint.
     */
    public function validateAnonymousBooking(Request $request): void
    {
        $fingerprint = $this->generateAnonymousFingerprint($request);

        $recentBooking = TherapySession::where('anonymous_fingerprint', $fingerprint)
            ->where('created_at', '>=', now()->subHours(24))
            ->whereNotIn('status', [TherapySessionStatus::CANCELLED, TherapySessionStatus::REJECTED])
            ->exists();

        if ($recentBooking) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                response()->json([
                    'success' => false,
                    'message' => 'An anonymous booking was recently made from this device. Please create an account to book additional sessions.',
                ], 422)
            );
        }
    }

    /**
     * Generate a SHA-256 fingerprint from the request's IP, user-agent, and
     * optional anonymous_session_id parameter.  Stable across the request
     * lifecycle; safe to call multiple times.
     */
    public function generateAnonymousFingerprint(Request $request): string
    {
        return hash('sha256',
            $request->ip() .
            $request->userAgent() .
            $request->input('anonymous_session_id', '')
        );
    }

    /**
     * Return true when the requested time slot overlaps an existing booking
     * for the given therapist.
     */
    public function checkDoubleBooking(int $therapistId, string $scheduledAt, int $durationMinutes): bool
    {
        $start = Carbon::parse($scheduledAt);
        $end = $start->copy()->addMinutes($durationMinutes);

        return TherapySession::where('therapist_id', $therapistId)
            ->whereNotIn('status', TherapySessionStatus::TERMINAL_STATUSES)
            ->where('scheduled_at', '<', $end)
            ->whereRaw('DATE_ADD(scheduled_at, INTERVAL COALESCE(duration_minutes, 60) MINUTE) > ?', [$start])
            ->exists();
    }

    /**
     * Enforce the 24-hour cancellation window.
     * Throws an HttpResponseException (HTTP 422) if the window has passed.
     */
    public function validateCancellationWindow(TherapySession $session): void
    {
        $scheduledAt = Carbon::parse($session->scheduled_at);
        if ($scheduledAt->diffInHours(now(), false) > -24) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Cancellations are only allowed up to 24 hours before the session.',
                ], 422)
            );
        }
    }
}
