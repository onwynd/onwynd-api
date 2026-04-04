<?php

namespace App\Events\AI;

use App\Models\AI\AIDiagnostic;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AIResponseGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $diagnostic;

    public $tokensUsed; // Placeholder for now

    public function __construct(AIDiagnostic $diagnostic, int $tokensUsed = 0)
    {
        $this->diagnostic = $diagnostic;
        $this->tokensUsed = $tokensUsed;
    }
}
