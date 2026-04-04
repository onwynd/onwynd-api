<?php

namespace App\Listeners\Therapy;

use App\Events\AI\RiskEscalationEvent;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

// use App\Notifications\Therapy\EmergencyRiskAlert; // Assuming this exists or we log

class HandleRiskDetection implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(RiskEscalationEvent $event): void
    {
        $diagnostic = $event->diagnostic;
        $user = $diagnostic->user;

        Log::critical('CRITICAL RISK DETECTED', [
            'user_id' => $user->id,
            'session_id' => $diagnostic->session_id,
            'risk_level' => $diagnostic->risk_level,
            'score' => $diagnostic->risk_score,
        ]);

        // 1. Notify Clinical Admin
        // Notification::route('mail', 'clinical-admin@onwynd.com')
        //     ->notify(new EmergencyRiskAlert($diagnostic));

        // 2. Notify On-Call Therapist if matched
        // ...

        // 3. Flag User Account for immediate review
        $user->update(['requires_clinical_review' => true]);
    }
}
