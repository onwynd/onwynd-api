<?php

namespace App\Listeners;

use App\Events\SessionCompleted;
use App\Services\TherapistCompensationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CalculateTherapistCommission implements ShouldQueue
{
    use InteractsWithQueue;

    protected TherapistCompensationService $compensationService;

    public function __construct(TherapistCompensationService $compensationService)
    {
        $this->compensationService = $compensationService;
    }

    public function handle(SessionCompleted $event): void
    {
        try {
            $session = $event->session;

            if ($session->status !== 'completed') {
                return;
            }

            Log::info('Calculating therapist commission for session', [
                'session_id' => $session->id,
                'session_uuid' => $session->uuid,
            ]);

            $payout = $this->compensationService->processSessionCompletion($session);

            Log::info('Therapist commission calculated and payout created', [
                'session_id' => $session->id,
                'payout_id' => $payout->id,
                'amount' => $payout->amount,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to calculate therapist commission', [
                'session_id' => $event->session->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
