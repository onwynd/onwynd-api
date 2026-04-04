<?php

namespace App\Repositories\Eloquent;

use App\Models\AI\AIConversation;
use App\Models\AI\AIDiagnostic;
use App\Repositories\Contracts\AIRepositoryInterface;
use Illuminate\Support\Str;

class AIEloquentRepository extends BaseRepository implements AIRepositoryInterface
{
    public function __construct(AIDiagnostic $model)
    {
        parent::__construct($model);
    }

    public function createDiagnosticSession(string $userId): AIDiagnostic
    {
        return $this->create([
            'user_id' => $userId,
            'session_id' => (string) Str::uuid(),
            'current_stage' => 'greeting',
            'status' => 'in_progress',
            'risk_level' => 'low',
        ]);
    }

    public function addMessage(string $diagnosticId, string $role, string $content, array $metadata = []): AIConversation
    {
        return AIConversation::create([
            'ai_diagnostic_id' => $diagnosticId,
            'role' => $role,
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }

    public function getDiagnosticWithHistory(string $sessionId, string $userId): ?AIDiagnostic
    {
        return $this->model->where('id', $sessionId)
            ->where('user_id', $userId)
            ->with(['conversations' => function ($q) {
                $q->orderBy('created_at', 'asc');
            }])
            ->first();
    }

    // Override find to support eager loading if needed, or just use getDiagnosticWithHistory
}
