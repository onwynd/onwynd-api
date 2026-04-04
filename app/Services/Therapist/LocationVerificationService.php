<?php

namespace App\Services\Therapist;

use App\Models\Therapist;
use App\Models\TherapistLocationMismatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class LocationVerificationService
{
    private const MISMATCH_THRESHOLD = 3;

    /**
     * Run a location check on therapist login.
     *
     * Records any country mismatch; flags the account and notifies admins
     * once MISMATCH_THRESHOLD unresolved mismatches accumulate within 30 days.
     *
     * Returns a warning array (with 'flagged' => true) if the threshold has
     * been reached, or null when no action is required.
     *
     * @return array{flagged: bool, message: string}|null
     */
    public function checkOnLogin(Therapist $therapist, Request $request): ?array
    {
        $storedCountry = strtoupper($therapist->country_of_operation ?? '');
        if (empty($storedCountry)) {
            return null; // No country stored — skip check
        }

        $detectedCountry = $this->detectCountry($request->ip());
        if (empty($detectedCountry) || $detectedCountry === $storedCountry) {
            // Match or geo-lookup failed — no flag needed
            return null;
        }

        // Record the mismatch
        TherapistLocationMismatch::create([
            'therapist_id'     => $therapist->id,
            'stored_country'   => $storedCountry,
            'detected_country' => $detectedCountry,
            'ip_address'       => $request->ip(),
            'detected_at'      => now(),
        ]);

        // Count recent unresolved mismatches (rolling 30-day window)
        $recentCount = TherapistLocationMismatch::where('therapist_id', $therapist->id)
            ->where('resolved', false)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($recentCount >= self::MISMATCH_THRESHOLD && ! $therapist->account_flagged) {
            $therapist->update([
                'account_flagged' => true,
                'flag_reason'     => 'location_mismatch',
                'flag_note'       => "Multiple logins detected from {$detectedCountry}. Stored country: {$storedCountry}.",
                'flagged_at'      => now(),
            ]);

            $this->notifyAdmin($therapist, $storedCountry, $detectedCountry, $recentCount, $request->ip());
            $this->notifyTherapist($therapist);

            Log::warning('Therapist location mismatch threshold reached', [
                'therapist_id'     => $therapist->id,
                'stored_country'   => $storedCountry,
                'detected_country' => $detectedCountry,
                'mismatch_count'   => $recentCount,
            ]);
        }

        if ($recentCount >= self::MISMATCH_THRESHOLD) {
            return [
                'flagged'  => true,
                'message'  => "We noticed you've been accessing Onwynd from a different location than your registered country of practice. If your practice location has changed, please contact support to update your account. If this is unexpected, please secure your account.",
            ];
        }

        return null;
    }

    /**
     * Resolve the country code for an IP using ip-api.com (free tier, no key needed for <45 req/min).
     * Returns null when the IP is private/reserved or the lookup fails.
     */
    private function detectCountry(string $ip): ?string
    {
        // Skip private / loopback / reserved ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        try {
            $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}?fields=countryCode,status");
            if ($response->ok()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'success') {
                    return strtoupper($data['countryCode'] ?? '');
                }
            }
        } catch (\Throwable $e) {
            Log::debug('IP geolocation lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Notify the therapist that their account has been flagged for review.
     * Uses the existing TherapistReverificationRequired notification.
     * Swallows any sending errors to prevent blocking the login response.
     */
    private function notifyTherapist(Therapist $therapist): void
    {
        try {
            if ($therapist->user) {
                $therapist->user->notify(new \App\Notifications\TherapistReverificationRequired($therapist));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to send therapist location flag notification', [
                'therapist_id' => $therapist->id,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch an inline mail notification to every admin user.
     * Swallows any sending errors to prevent blocking the login response.
     */
    private function notifyAdmin(
        Therapist $therapist,
        string $stored,
        string $detected,
        int $count,
        string $ip
    ): void {
        try {
            $adminUsers = \App\Models\User::whereHas(
                'roles',
                fn ($q) => $q->where('slug', 'admin')
            )->get();

            if ($adminUsers->isEmpty()) {
                return;
            }

            Notification::send(
                $adminUsers,
                new class ($therapist, $stored, $detected, $count, $ip) extends \Illuminate\Notifications\Notification
                {
                    public function __construct(
                        private readonly Therapist $therapist,
                        private readonly string $stored,
                        private readonly string $detected,
                        private readonly int $count,
                        private readonly string $ip,
                    ) {}

                    /** @return string[] */
                    public function via(mixed $notifiable): array
                    {
                        return ['mail'];
                    }

                    public function toMail(mixed $notifiable): \Illuminate\Notifications\Messages\MailMessage
                    {
                        $firstName = $this->therapist->user?->first_name ?? '';
                        $lastName  = $this->therapist->user?->last_name ?? '';
                        $name      = trim("{$firstName} {$lastName}") ?: 'Unknown Therapist';

                        return (new \Illuminate\Notifications\Messages\MailMessage)
                            ->subject('Location Mismatch Alert — Therapist Account Review Required')
                            ->greeting('Admin Alert')
                            ->line("Therapist **{$name}** has triggered the location mismatch threshold.")
                            ->line("Stored country: **{$this->stored}** | Detected country: **{$this->detected}**")
                            ->line("Consecutive mismatches in last 30 days: **{$this->count}** | Last IP: {$this->ip}")
                            ->action('Review Therapist', url('/admin/therapists/'.$this->therapist->id))
                            ->line('Recommended actions: Dismiss (legitimate travel), Request re-verification, or Suspend pending review.');
                    }
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send admin location mismatch notification', [
                'therapist_id' => $therapist->id,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
