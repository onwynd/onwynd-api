<?php

namespace App\Jobs;

use App\Mail\UserInactivityRetentionEmail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserInactivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The user instance.
     *
     * @var \App\Models\User
     */
    protected $user;

    /**
     * Number of days the user has been inactive.
     *
     * @var int
     */
    protected $daysInactive;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, int $daysInactive)
    {
        $this->user = $user;
        $this->daysInactive = $daysInactive;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if ($this->userIsStillInactive()) {
                Log::info('Sending user inactivity retention email', [
                    'user_id' => $this->user->id,
                    'email' => $this->user->email,
                    'days_inactive' => $this->daysInactive,
                ]);
                Mail::to($this->user->email)->send(new UserInactivityRetentionEmail($this->user, $this->daysInactive));
                $this->trackRetentionAttempt();
            }
        } catch (\Exception $e) {
            Log::error('Failed to send user inactivity retention email', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);
            $this->fail($e);
        }
    }

    /**
     * Check if the user is still inactive before sending the email.
     */
    private function userIsStillInactive(): bool
    {
        $cutoffDate = Carbon::now()->subDays($this->daysInactive)->startOfDay();

        // Check mindfulness activities
        $hasRecentMindfulness = $this->user->mindfulnessActivities()
            ->where('completed_at', '>=', $cutoffDate)
            ->exists();

        if ($hasRecentMindfulness) {
            return false;
        }

        // Check mood trackings
        $hasRecentMood = $this->user->moodTrackings()
            ->where('logged_at', '>=', $cutoffDate)
            ->exists();

        if ($hasRecentMood) {
            return false;
        }

        return true;
    }

    /**
     * Track that we've attempted to retain this user to avoid spam.
     */
    private function trackRetentionAttempt(): void
    {
        // Cache for 7 days to prevent multiple retention emails
        Cache::put(
            "retention_attempt:{$this->user->id}:".now()->format('Y-m-d'),
            true,
            now()->addDays(7)
        );
    }
}
