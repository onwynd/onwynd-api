<?php

namespace App\Services\AI;

use App\Enums\RiskLevel;
use App\Events\AI\AIResponseGenerated;
use App\Events\AI\RiskEscalationEvent;
use App\Models\AI\AIDiagnostic;
use App\Models\User;
use App\Repositories\Contracts\AIRepositoryInterface;
use Illuminate\Support\Facades\Log;

class AIDiagnosticService
{
    protected $providerFactory;

    protected $promptManager;

    protected $riskService;

    protected $aiRepository;

    protected $stages = [
        'greeting',
        'symptom_exploration',
        'duration_intensity',
        'impact_daily_life',
        'coping_mechanisms',
        'support_system',
        'risk_assessment',
        'summary_recommendations',
        'completed',
    ];

    public function __construct(
        AIProviderFactory $providerFactory,
        AIDiagnosticPromptManager $promptManager,
        RiskDetectionService $riskService,
        AIRepositoryInterface $aiRepository
    ) {
        $this->providerFactory = $providerFactory;
        $this->promptManager = $promptManager;
        $this->riskService = $riskService;
        $this->aiRepository = $aiRepository;
    }

    public function startDiagnostic(User $user)
    {
        $diagnostic = $this->aiRepository->createDiagnosticSession($user->id);

        // Generate initial greeting
        $response = $this->generateAIResponse($diagnostic, 'Generate initial greeting');

        if (! $response) {
            $response = 'Hello. I am Dr. Aura. How can I help you today?';
        }

        $this->aiRepository->addMessage($diagnostic->id, 'assistant', $response);

        return $diagnostic->load('conversations');
    }

    public function processUserResponse(AIDiagnostic $diagnostic, string $userMessage)
    {
        // 1. Save User Message
        $this->aiRepository->addMessage($diagnostic->id, 'user', $userMessage);

        // 2. Risk Detection
        $riskAnalysis = $this->riskService->analyze($userMessage);

        if ($riskAnalysis['requires_escalation']) {
            $this->aiRepository->update($diagnostic->id, [
                'status' => 'escalated',
                'risk_level' => RiskLevel::SEVERE,
                'risk_score' => $diagnostic->risk_score + $riskAnalysis['score'],
            ]);

            // Trigger Event for Listener
            event(new RiskEscalationEvent($diagnostic));

            $escalationMsg = 'I am detecting some serious concerns for your safety. Please contact emergency services or call 988 immediately. I am providing you with local emergency contacts now.';
            $this->aiRepository->addMessage($diagnostic->id, 'assistant', $escalationMsg);

            return $diagnostic->fresh(['conversations']);
        }

        // Update Risk Score
        $this->aiRepository->update($diagnostic->id, [
            'risk_score' => $diagnostic->risk_score + $riskAnalysis['score'],
            'risk_level' => $this->determineRiskLevel($diagnostic->risk_score + $riskAnalysis['score']),
        ]);

        // 3. Advance Stage Logic (Simplified)
        $nextStage = $this->getNextStage($diagnostic->current_stage);

        if ($diagnostic->current_stage !== $nextStage) {
            $this->aiRepository->update($diagnostic->id, ['current_stage' => $nextStage]);
        }

        if ($nextStage === 'completed') {
            $this->aiRepository->update($diagnostic->id, ['status' => 'completed']);
            // Generate summary
            $summary = $this->generateAIResponse($diagnostic, 'Generate a clinical summary and recommendations based on the conversation history.');
            $this->aiRepository->addMessage($diagnostic->id, 'assistant', $summary);
            $this->aiRepository->update($diagnostic->id, ['summary' => ['text' => $summary]]);

            return $diagnostic->fresh(['conversations']);
        }

        // 4. Generate AI Response for current stage
        $aiResponse = $this->generateAIResponse($diagnostic, $userMessage);
        $this->aiRepository->addMessage($diagnostic->id, 'assistant', $aiResponse);

        return $diagnostic->fresh(['conversations']);
    }

    protected function generateAIResponse(AIDiagnostic $diagnostic, string $lastUserMessage): string
    {
        $provider = $this->providerFactory->makeForTask('complex');

        $history = $diagnostic->conversations()->orderBy('created_at')->get()->map(function ($c) {
            return ['role' => $c->role, 'content' => $c->content];
        })->toArray();

        $systemPrompt = $this->promptManager->getPrompt($diagnostic->current_stage);

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $history
        );

        if (count($history) === 0 && $diagnostic->current_stage === 'greeting') {
            $messages[] = ['role' => 'user', 'content' => $lastUserMessage];
        }

        try {
            $response = $provider->chat($messages, ['temperature' => 0.7]);

            event(new AIResponseGenerated($diagnostic, str_word_count($response) * 1.3));

            return $response;
        } catch (\Exception $e) {
            Log::error('AI Generation Failed: '.$e->getMessage());

            return "I apologize, but I'm having trouble connecting right now. Let's take a pause. How are you feeling otherwise?";
        }
    }

    protected function getNextStage(string $currentStage): string
    {
        $index = array_search($currentStage, $this->stages);
        if ($index !== false && isset($this->stages[$index + 1])) {
            return $this->stages[$index + 1];
        }

        return 'completed';
    }

    protected function determineRiskLevel(int $score): RiskLevel
    {
        return match (true) {
            $score >= 80 => RiskLevel::CRITICAL,
            $score >= 50 => RiskLevel::SEVERE,
            $score >= 30 => RiskLevel::HIGH,
            $score >= 10 => RiskLevel::MODERATE,
            default => RiskLevel::LOW
        };
    }
}
