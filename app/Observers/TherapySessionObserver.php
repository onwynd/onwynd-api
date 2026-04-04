<?php

namespace App\Observers;

use App\Events\SessionCancelled;
use App\Events\SessionCompleted;
use App\Events\SessionCreated;
use App\Models\TherapySession;
use Illuminate\Support\Facades\Log;

class TherapySessionObserver
{
    /**
     * Handle the TherapySession "created" event
     */
    public function created(TherapySession $session): void
    {
        Log::info('Session created', ['session_id' => $session->id, 'user_id' => $session->user_id]);

        // Dispatch event
        event(new SessionCreated($session));
    }

    /**
     * Handle the TherapySession "updated" event
     */
    public function updated(TherapySession $session): void
    {
        Log::info('Session updated', ['session_id' => $session->id]);

        // Check if status changed to completed
        if ($session->isDirty('status') && $session->status === 'completed') {
            event(new SessionCompleted($session));
        }

        // Check if status changed to cancelled
        if ($session->isDirty('status') && $session->status === 'cancelled') {
            event(new SessionCancelled($session));
        }
    }

    /**
     * Handle the TherapySession "deleted" event
     */
    public function deleted(TherapySession $session): void
    {
        Log::warning('Session deleted', ['session_id' => $session->id]);
    }

    /**
     * Handle the TherapySession "restored" event
     */
    public function restored(TherapySession $session): void
    {
        Log::info('Session restored', ['session_id' => $session->id]);
    }

    /**
     * Handle the TherapySession "force deleted" event
     */
    public function forceDeleted(TherapySession $session): void
    {
        Log::warning('Session force deleted', ['session_id' => $session->id]);
    }
}
