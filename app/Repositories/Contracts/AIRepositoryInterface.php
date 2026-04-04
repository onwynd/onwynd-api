<?php

namespace App\Repositories\Contracts;

use App\Models\AI\AIConversation;
use App\Models\AI\AIDiagnostic;

interface AIRepositoryInterface extends BaseRepositoryInterface
{
    public function createDiagnosticSession(string $userId): AIDiagnostic;

    public function addMessage(string $diagnosticId, string $role, string $content, array $metadata = []): AIConversation;

    public function getDiagnosticWithHistory(string $sessionId, string $userId): ?AIDiagnostic;
}
