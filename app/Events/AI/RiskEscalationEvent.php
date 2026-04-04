<?php

namespace App\Events\AI;

use App\Models\AI\AIDiagnostic;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiskEscalationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $diagnostic;

    public function __construct(AIDiagnostic $diagnostic)
    {
        $this->diagnostic = $diagnostic;
    }
}
