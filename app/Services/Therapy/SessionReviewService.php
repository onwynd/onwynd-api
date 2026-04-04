<?php

namespace App\Services\Therapy;

use App\Models\Therapy\SessionReview;
use App\Models\TherapySession;
use App\Services\AI\AIService;
use App\Services\AI\Prompts\SessionAnalysisPrompt;
use Illuminate\Support\Facades\Log;

class SessionReviewService
{
    protected $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Analyze a session transcript and store the review
     */
    public function analyzeSession(TherapySession $session, string $transcript)
    {
        $startTime = microtime(true);

        try {
            $systemPrompt = SessionAnalysisPrompt::getSystemPrompt();
            $userPrompt = SessionAnalysisPrompt::getUserPrompt($transcript, [
                'session_type' => $session->session_type,
                'duration' => $session->duration_minutes,
                'patient_id' => $session->patient_id,
                'therapist_id' => $session->therapist_id,
            ]);

            // Construct history with system prompt
            $history = [
                ['role' => 'system', 'content' => $systemPrompt],
            ];

            $response = $this->aiService->generateResponse($userPrompt, $history);

            // Clean response if it contains markdown code blocks
            $cleanedResponse = $this->cleanJson($response);
            $analysis = json_decode($cleanedResponse, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON from AI: '.json_last_error_msg());
            }

            $processingTime = microtime(true) - $startTime;

            return $this->storeReview($session, $analysis, $processingTime, $cleanedResponse);

        } catch (\Exception $e) {
            Log::error('Session Analysis Failed: '.$e->getMessage());
            throw $e;
        }
    }

    protected function storeReview(TherapySession $session, array $data, float $processingTime, string $rawResponse)
    {
        return SessionReview::create([
            'therapy_session_id' => $session->id,
            'therapist_id' => $session->therapist_id,
            'user_id' => $session->patient_id,
            'risk_level' => $data['risk_level'] ?? 'low',
            'risk_flags' => $data['risk_flags'] ?? [],
            'risk_summary' => $data['risk_summary'] ?? '',
            'recommended_action' => $data['recommended_action'] ?? 'none',
            'risk_confidence_score' => $data['risk_confidence_score'] ?? 0,

            'empathy_score' => $data['empathy_score'] ?? 0,
            'clinical_accuracy_score' => $data['clinical_accuracy_score'] ?? 0,
            'directiveness_score' => $data['directiveness_score'] ?? 0,
            'pacing_score' => $data['pacing_score'] ?? 0,
            'overall_session_quality_score' => $data['overall_session_quality_score'] ?? 0,
            'strengths' => $data['strengths'] ?? [],
            'opportunities' => $data['opportunities'] ?? [],

            'predicted_improvement_percentage' => $data['predicted_improvement_percentage'] ?? null,
            'outcome_confidence_score' => $data['outcome_confidence_score'] ?? null,
            'success_factors' => $data['success_factors'] ?? [],
            'risk_factors' => $data['risk_factors'] ?? [],

            'treatment_alignment_score' => $data['treatment_alignment_score'] ?? null,
            'addressed_treatment_goals' => $data['addressed_treatment_goals'] ?? false,
            'homework_completed' => $data['homework_completed'] ?? false,
            'recommendations' => $data['recommendations'] ?? [],

            'compliance_score' => $data['compliance_score'] ?? null,
            'compliance_flags' => $data['compliance_flags'] ?? [],
            'compliance_notes' => $data['compliance_notes'] ?? null,

            'review_status' => $this->determineInitialStatus($data['risk_level']),
            'ai_model_used' => config('services.ai.default', 'openai'),
            'processing_time_seconds' => (int) $processingTime,
            'full_ai_response' => json_decode($rawResponse, true), // Ensure valid JSON
        ]);
    }

    protected function determineInitialStatus($riskLevel)
    {
        return in_array($riskLevel, ['high', 'critical']) ? 'flagged' : 'pending';
    }

    protected function cleanJson($string)
    {
        // Remove markdown code blocks if present
        $string = preg_replace('/^```json\s*/', '', $string);
        $string = preg_replace('/^```\s*/', '', $string);
        $string = preg_replace('/\s*```$/', '', $string);

        return trim($string);
    }
}
