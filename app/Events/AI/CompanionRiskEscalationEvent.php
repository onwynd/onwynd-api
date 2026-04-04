<?php

namespace App\Events\AI;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CompanionRiskEscalationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $user_id;

    public string $session_id;

    public string $message;

    public array $risk;

    public function __construct(int $user_id, string $session_id, string $message, array $risk)
    {
        $this->user_id = $user_id;
        $this->session_id = $session_id;
        $this->message = $message;
        $this->risk = $risk;
    }
}
