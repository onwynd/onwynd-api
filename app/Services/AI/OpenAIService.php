<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    protected $baseUrl;

    protected $apiKey;

    protected $model;

    public function __construct()
    {
        $this->baseUrl = 'https://api.openai.com/v1';
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4-turbo-preview');
    }

    /**
     * Generate a response from OpenAI
     *
     * @param  string  $prompt
     * @param  array  $history  Chat history for context
     * @return string|null
     */
    public function generateResponse($prompt, $history = [])
    {
        try {
            $messages = $history;
            $messages[] = ['role' => 'user', 'content' => $prompt];

            // Ensure system message exists if history is empty or doesn't have one
            if (empty($history) || $history[0]['role'] !== 'system') {
                array_unshift($messages, [
                    'role' => 'system',
                    'content' => 'You are Onwynd AI, a mental health assistant. Provide empathetic, professional, and safe support. If a user expresses self-harm or severe distress, direct them to emergency resources immediately.',
                ]);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 500,
            ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            Log::error('OpenAI Error: '.$response->body());

            return null;
        } catch (\Exception $e) {
            Log::error('OpenAI Exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Analyze sentiment of a text
     *
     * @param  string  $text
     * @return array
     */
    public function analyzeSentiment($text)
    {
        $prompt = "Analyze the sentiment of the following text and return a JSON object with 'sentiment' (positive, negative, neutral), 'score' (0-10), and 'risk_level' (low, medium, high): \n\n\"{$text}\"";

        $response = $this->generateResponse($prompt);

        if ($response) {
            // Attempt to parse JSON from response
            preg_match('/\{.*\}/s', $response, $matches);
            if (! empty($matches[0])) {
                return json_decode($matches[0], true);
            }
        }

        return ['sentiment' => 'neutral', 'score' => 5, 'risk_level' => 'low'];
    }

    /**
     * Generate interpretation and recommendations for an assessment.
     * Falls back to evidence-based static recommendations when AI is unavailable.
     *
     * @return array{interpretation: string, recommendations: string[]}
     */
    public function generateAssessmentInterpretation(
        string $assessmentName,
        int $score,
        array $answers,
        float $percentage = 0.0,
        ?string $severity = null
    ): array {
        $severityText = $severity ? "The severity classification is: {$severity}." : '';
        $prompt = <<<PROMPT
You are a compassionate mental health professional writing personalised assessment feedback for a user on the Onwynd app.

Assessment: {$assessmentName}
Score: {$score} ({$percentage}%)
{$severityText}

Instructions:
1. Write a warm, empathetic 2-3 sentence "interpretation" that acknowledges the user's results without catastrophising, and validates their experience.
2. Provide exactly 5 specific, actionable "recommendations" tailored to this severity level and assessment type (e.g. for depression/PHQ-9 address mood, energy, social connection; for anxiety/GAD-7 address worry, breathing, grounding; for stress/PSS-10 address workload, coping; for well-being/WHO-5 address positive activities, sleep).
3. If severity is Moderate or higher, include a gentle recommendation to speak with a professional.
4. Return ONLY a valid JSON object — no markdown, no extra text — with keys "interpretation" (string) and "recommendations" (array of exactly 5 strings).
PROMPT;

        $response = $this->generateResponse($prompt);

        if ($response) {
            // Strip markdown code fences if present
            $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($response));
            $clean = preg_replace('/\s*```$/', '', $clean);
            preg_match('/\{.*\}/s', $clean, $matches);
            if (! empty($matches[0])) {
                $parsed = json_decode($matches[0], true);
                if (is_array($parsed) && isset($parsed['interpretation'], $parsed['recommendations'])) {
                    return $parsed;
                }
            }
        }

        // Evidence-based fallback based on assessment type and severity
        return $this->getFallbackInterpretation($assessmentName, $score, $severity);
    }

    /**
     * Evidence-based fallback recommendations when AI is unavailable.
     */
    private function getFallbackInterpretation(string $assessmentName, int $score, ?string $severity): array
    {
        $title = strtolower($assessmentName);
        $sev = strtolower($severity ?? '');

        // PHQ-9 Depression
        if (str_contains($title, 'phq') || str_contains($title, 'depression')) {
            return match (true) {
                $score >= 20 => [
                    'interpretation' => 'Your responses suggest you may be experiencing severe depressive symptoms. This is a significant level of distress, and it takes courage to acknowledge it. You deserve support right now.',
                    'recommendations' => [
                        'Please reach out to a mental health professional or crisis support line as soon as possible',
                        'Talk to someone you trust — a friend, family member, or therapist — about how you\'re feeling',
                        'Avoid isolating yourself; gentle social contact can provide comfort even when it feels hard',
                        'Engage in a simple self-care routine: regular meals, hydration, and short walks outside',
                        'Book a session with a licensed therapist on Onwynd to begin professional support',
                    ],
                ],
                $score >= 10 => [
                    'interpretation' => 'Your results indicate moderate depressive symptoms that are affecting your daily life. These feelings are real and valid, and there are effective strategies that can help you feel better.',
                    'recommendations' => [
                        'Consider speaking with a therapist — cognitive-behavioural therapy (CBT) is highly effective for moderate depression',
                        'Establish a daily routine with consistent sleep and wake times to regulate your mood',
                        'Incorporate 20–30 minutes of moderate exercise daily, which has proven antidepressant effects',
                        'Limit alcohol and reduce screen time before bed to improve sleep quality',
                        'Practice a daily gratitude journal — note 3 small positive moments each day',
                    ],
                ],
                $score >= 5 => [
                    'interpretation' => 'You\'re experiencing mild depressive symptoms. Your awareness is a positive first step. These feelings can often be managed effectively with lifestyle changes and self-care.',
                    'recommendations' => [
                        'Prioritise regular physical activity — even 15 minutes of walking can lift your mood',
                        'Maintain social connections; reach out to a friend or family member this week',
                        'Try mindfulness meditation using the Onwynd Unwind Hub to reduce low mood',
                        'Set one small achievable goal each day to build a sense of accomplishment',
                        'Monitor your mood with the Onwynd mood tracker to identify patterns and triggers',
                    ],
                ],
                default => [
                    'interpretation' => 'Your responses suggest minimal depressive symptoms — you\'re managing well right now. Keep up the positive habits you have in place.',
                    'recommendations' => [
                        'Continue your regular physical activity and healthy sleep schedule',
                        'Practice gratitude and mindfulness to maintain your emotional resilience',
                        'Stay socially connected with friends and family',
                        'Check in with yourself regularly using the Onwynd mood tracker',
                        'Explore the Onwynd Unwind Hub for relaxation and well-being resources',
                    ],
                ],
            };
        }

        // GAD-7 Anxiety
        if (str_contains($title, 'gad') || str_contains($title, 'anxiety')) {
            return match (true) {
                $score >= 15 => [
                    'interpretation' => 'Your responses indicate severe anxiety symptoms that are likely having a significant impact on your daily functioning. What you\'re experiencing is real, and effective help is available.',
                    'recommendations' => [
                        'Seek professional support promptly — a therapist can provide evidence-based treatments such as CBT for anxiety',
                        'Practice diaphragmatic breathing: inhale for 4 counts, hold for 2, exhale for 6 to activate your parasympathetic nervous system',
                        'Reduce caffeine intake, which can amplify anxiety and worsen physical symptoms',
                        'Identify your top anxiety triggers by journaling and work with a therapist to develop coping strategies',
                        'Book a session with a therapist on Onwynd to create a personalised anxiety management plan',
                    ],
                ],
                $score >= 10 => [
                    'interpretation' => 'Your results show moderate anxiety that is interfering with your daily life. Anxiety at this level is treatable and many people find significant relief with the right support.',
                    'recommendations' => [
                        'Try the 5-4-3-2-1 grounding technique when anxiety spikes: name 5 things you see, 4 you can touch, 3 you hear, 2 you smell, 1 you taste',
                        'Limit news and social media consumption to reduce worry-inducing stimuli',
                        'Practice progressive muscle relaxation before bed to release physical tension',
                        'Schedule "worry time" — a 15-minute window to process worries, then deliberately set them aside',
                        'Consider speaking with a therapist about CBT or acceptance-based approaches',
                    ],
                ],
                $score >= 5 => [
                    'interpretation' => 'You\'re experiencing mild anxiety symptoms. It\'s good that you\'re tuned in to how you\'re feeling. Simple coping tools can make a meaningful difference.',
                    'recommendations' => [
                        'Practice box breathing daily: 4 counts in, hold 4, out 4, hold 4',
                        'Establish a consistent sleep schedule — poor sleep is a major anxiety amplifier',
                        'Reduce unnecessary commitments to lower overall stress levels',
                        'Use the Onwynd breathing exercises before stressful situations',
                        'Maintain regular physical activity to burn off excess adrenaline',
                    ],
                ],
                default => [
                    'interpretation' => 'Your responses suggest minimal anxiety — you\'re coping well at the moment. Your self-awareness in completing this assessment is commendable.',
                    'recommendations' => [
                        'Maintain your current healthy coping strategies',
                        'Continue regular exercise and sufficient sleep',
                        'Practice mindfulness to sustain your mental well-being',
                        'Check in with yourself periodically using the Onwynd assessment tool',
                        'Share your well-being journey with the Onwynd community for support',
                    ],
                ],
            };
        }

        // PSS-10 Stress
        if (str_contains($title, 'pss') || str_contains($title, 'stress')) {
            return match (true) {
                $score >= 27 => [
                    'interpretation' => 'Your responses indicate high perceived stress. You\'re carrying a heavy load right now, and it\'s taking a real toll on your wellbeing. You don\'t have to navigate this alone.',
                    'recommendations' => [
                        'Prioritise identifying and reducing your primary stressors — consider which commitments you can delegate or eliminate',
                        'Schedule regular recovery time into your day, including breaks and non-negotiable rest periods',
                        'Try a body scan meditation to release physical stress — available in the Onwynd Unwind Hub',
                        'Speak with a therapist about stress management strategies and setting healthy boundaries',
                        'Reach out to your support network — sharing your burden with trusted people can significantly reduce perceived stress',
                    ],
                ],
                $score >= 14 => [
                    'interpretation' => 'Your stress levels are in the moderate range. While you\'re managing, there are clear opportunities to build more resilience and ease your mental load.',
                    'recommendations' => [
                        'Practice time-blocking to create structure and reduce decision fatigue',
                        'Incorporate a daily 10-minute mindfulness practice into your routine',
                        'Identify the top 2-3 stressors this week and make a concrete plan to address one of them',
                        'Exercise at least 3 times a week — it is one of the most effective stress reducers',
                        'Use the Pomodoro technique in Onwynd\'s Unwind Hub to improve focused work and recovery',
                    ],
                ],
                default => [
                    'interpretation' => 'Your stress levels appear well-managed right now. This is a great foundation to build on. Keeping these habits in place will support your long-term resilience.',
                    'recommendations' => [
                        'Maintain your current stress management strategies',
                        'Continue regular exercise and mindfulness practices',
                        'Check in with your stress levels monthly using this assessment',
                        'Explore the Onwynd Unwind Hub to discover new relaxation tools',
                        'Build on your resilience by connecting with supportive people regularly',
                    ],
                ],
            };
        }

        // WHO-5 Well-being
        if (str_contains($title, 'who') || str_contains($title, 'well-being') || str_contains($title, 'wellbeing')) {
            return match (true) {
                $percentage <= 50 => [
                    'interpretation' => 'Your well-being score suggests you may be experiencing some challenges with your overall quality of life and emotional health. Recognising this is an important first step toward positive change.',
                    'recommendations' => [
                        'Schedule a consultation with a therapist to explore what may be contributing to lower well-being',
                        'Identify activities that previously brought you joy and gradually reintroduce one this week',
                        'Prioritise sleep hygiene — consistent sleep times and a calming bedtime routine',
                        'Spend time in nature daily, even briefly — exposure to natural environments boosts well-being',
                        'Practice self-compassion: treat yourself with the same kindness you would offer a close friend',
                    ],
                ],
                $percentage <= 75 => [
                    'interpretation' => 'You have a moderate sense of well-being. There is meaningful room to enhance your emotional vitality and life satisfaction with some targeted changes.',
                    'recommendations' => [
                        'Cultivate positive emotions by engaging in activities you find meaningful or pleasurable each day',
                        'Strengthen your relationships — quality social connection is one of the strongest predictors of well-being',
                        'Set one meaningful personal goal and track your progress toward it',
                        'Incorporate gratitude practices: reflect on 3 things you appreciate about your life each evening',
                        'Try the guided soundscapes in Onwynd\'s Unwind Hub for daily emotional restoration',
                    ],
                ],
                default => [
                    'interpretation' => 'Your well-being score reflects a strong sense of emotional health and life satisfaction — well done! Your habits and mindset are clearly working well for you.',
                    'recommendations' => [
                        'Continue nurturing the relationships and activities that contribute to your flourishing',
                        'Share what works for you with the Onwynd community to inspire others',
                        'Maintain your current self-care practices',
                        'Set stretch goals to keep growing personally and professionally',
                        'Re-take this assessment periodically to monitor your well-being over time',
                    ],
                ],
            };
        }

        // Generic fallback
        $severityLabel = $severity ?? 'Moderate';

        return [
            'interpretation' => "Your assessment results show {$severityLabel} levels on {$assessmentName}. Your willingness to reflect on your mental health is a powerful act of self-awareness and care.",
            'recommendations' => [
                'Consider speaking with a mental health professional to discuss your results in detail',
                'Practice daily mindfulness meditation using the Onwynd Unwind Hub',
                'Maintain a consistent sleep schedule of 7-9 hours per night',
                'Engage in regular physical activity — aim for at least 30 minutes most days',
                'Connect with supportive people in your life and consider journaling your experiences',
            ],
        ];
    }
}
