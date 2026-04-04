<?php

namespace App\Listeners;

use App\Events\AssessmentCompleted;
use App\Mail\AssessmentResultEmail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAssessmentResultEmail implements ShouldQueue
{
    /**
     * Severity levels that indicate the user is doing well — no need to email.
     * We only send when there is something meaningful to flag or act on.
     */
    private const SKIP_SEVERITIES = [
        'Minimal',       // PHQ-9 / GAD-7 — no significant symptoms
        'Low',           // PSS-10 — low stress
        'High Well-being', // WHO-5 — already thriving
    ];

    public function handle(AssessmentCompleted $event): void
    {
        $result = $event->result;
        $assessment = $event->assessment;

        /** @var User $user */
        $user = $result->user;

        // Skip if user is anonymous or has no real email
        if (! $user || ! $user->email || str_ends_with($user->email, '@anonymous.onwynd.com')) {
            return;
        }

        // Skip benign results — no value in emailing someone who's doing fine
        $severity = $result->severity_level;
        if ($severity && in_array($severity, self::SKIP_SEVERITIES, true)) {
            Log::info('AssessmentResultEmail: skipping benign result', [
                'user_id' => $user->id,
                'assessment' => $assessment->title,
                'severity' => $severity,
            ]);

            return;
        }

        // Skip if no severity was determined (shouldn't happen, but be safe)
        if (empty($severity)) {
            return;
        }

        try {
            Mail::to($user->email)->queue(new AssessmentResultEmail($result, $assessment));
        } catch (\Throwable $e) {
            Log::error('AssessmentResultEmail: failed to queue', [
                'user_id' => $user->id,
                'result_id' => $result->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
