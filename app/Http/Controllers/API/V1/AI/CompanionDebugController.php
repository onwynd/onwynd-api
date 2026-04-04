<?php

namespace App\Http\Controllers\API\V1\AI;

use App\Events\AI\CompanionRiskEscalationEvent;
use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CompanionDebugController extends BaseController
{
    public function trigger(Request $request)
    {
        $user = $request->user();
        $message = (string) ($request->input('message') ?: 'I want to harm myself and end my life.');
        $sessionId = (string) Str::uuid();
        $risk = [
            'requires_escalation' => true,
            'risk_level' => 'critical',
            'score' => 95,
            'flags' => ['explicit_self_harm', 'suicidal_intent'],
        ];

        event(new CompanionRiskEscalationEvent($user->id, $sessionId, $message, $risk));

        Log::info('Companion risk escalation test dispatched', [
            'admin_id' => $user->id,
            'session_id' => $sessionId,
        ]);

        return $this->sendResponse([
            'dispatched' => true,
            'session_id' => $sessionId,
            'risk' => $risk,
        ], 'Companion risk escalation event dispatched');
    }
}
