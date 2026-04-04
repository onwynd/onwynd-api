<?php

namespace App\Listeners\AI;

use App\Events\AI\AIResponseGenerated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogAIUsage implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(AIResponseGenerated $event): void
    {
        // Logic to log token usage to a database or monitoring service
        // For MVP, we'll log to the file
        Log::info('AI Usage Logged', [
            'diagnostic_id' => $event->diagnostic->id,
            'user_id' => $event->diagnostic->user_id,
            'tokens' => $event->tokensUsed,
            'timestamp' => now(),
        ]);

        // In production: DB::table('ai_usage_logs')->insert(...)
    }
}
