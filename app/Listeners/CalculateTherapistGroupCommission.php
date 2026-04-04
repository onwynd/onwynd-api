<?php

namespace App\Listeners;

use App\Events\GroupSessionCompleted;
use App\Services\TherapistCompensationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class CalculateTherapistGroupCommission implements ShouldQueue
{
    protected $compensationService;

    /**
     * Create the event listener.
     */
    public function __construct(TherapistCompensationService $compensationService)
    {
        $this->compensationService = $compensationService;
    }

    /**
     * Handle the event.
     */
    public function handle(GroupSessionCompleted $event): void
    {
        try {
            $this->compensationService->processGroupSessionCompletion($event->session);
        } catch (\Exception $e) {
            Log::error("Failed to calculate commission for group session {$event->session->uuid}: ".$e->getMessage());
        }
    }
}
