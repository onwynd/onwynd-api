<?php

namespace App\Services\AI;

use App\Models\DailyTip;
use Illuminate\Support\Facades\Log;

class DailyTipService
{
    protected AIProviderFactory $aiFactory;

    public function __construct(AIProviderFactory $aiFactory)
    {
        $this->aiFactory = $aiFactory;
    }

    /**
     * Get or generate the daily tip for today
     */
    public function getTodayTip(): ?DailyTip
    {
        // First, try to get today's pre-scheduled tip
        $todayTip = DailyTip::active()->forToday()->first();

        if ($todayTip) {
            return $todayTip;
        }

        // If no pre-scheduled tip, generate a new one
        return $this->generateNewTip();
    }

    /**
     * Generate a new AI-powered daily tip
     */
    public function generateNewTip(?string $category = null): ?DailyTip
    {
        try {
            $category = $category ?? $this->getRandomCategory();

            $prompt = $this->buildPrompt($category);
            $provider = $this->aiFactory->make();
            $aiResponse = $provider->chat([['role' => 'user', 'content' => $prompt]]);

            if (! $aiResponse) {
                Log::warning('Failed to generate AI tip, using fallback');

                return $this->getFallbackTip($category);
            }

            return $this->parseAndStoreTip($aiResponse, $category);
        } catch (\Exception $e) {
            Log::error('Error generating daily tip: '.$e->getMessage());

            return $this->getFallbackTip($category);
        }
    }

    /**
     * Build the AI prompt for generating tips
     */
    protected function buildPrompt(string $category): string
    {
        $basePrompt = 'Generate a concise, actionable mental health tip for today. ';

        $categoryPrompts = [
            'anxiety' => 'Focus on anxiety management. Include a specific technique or grounding exercise. Keep it practical and encouraging.',
            'stress' => 'Focus on stress reduction. Include a simple daily practice or mindset shift. Make it relatable.',
            'mood' => 'Focus on mood improvement. Include a positive psychology technique or gratitude practice. Keep it uplifting.',
            'sleep' => 'Focus on sleep hygiene. Include a bedtime routine tip or relaxation technique. Make it practical.',
            'mindfulness' => 'Focus on mindfulness and presence. Include a simple meditation or awareness exercise. Keep it accessible.',
            'self-care' => 'Focus on self-care practices. Include a daily habit or self-compassion technique. Make it nurturing.',
        ];

        $categorySpecific = $categoryPrompts[$category] ?? $categoryPrompts['anxiety'];

        return $basePrompt.$categorySpecific.' Format as JSON with keys: tip (string), technique (string), category (string), and optional_metadata (object with steps or additional info). Keep the tip under 200 characters and actionable.';
    }

    /**
     * Parse AI response and store the tip
     */
    protected function parseAndStoreTip(string $aiResponse, string $category): ?DailyTip
    {
        try {
            // Try to extract JSON from the response
            $jsonMatch = preg_match('/\{[\s\S]*\}/', $aiResponse, $matches);
            if (! $jsonMatch) {
                // If no JSON found, create a simple tip from the text
                return $this->createSimpleTip($aiResponse, $category);
            }

            $tipData = json_decode($matches[0], true);
            if (! $tipData || ! isset($tipData['tip'])) {
                return $this->createSimpleTip($aiResponse, $category);
            }

            return DailyTip::create([
                'tip' => $tipData['tip'],
                'category' => $tipData['category'] ?? $category,
                'technique' => $tipData['technique'] ?? null,
                'metadata' => $tipData['optional_metadata'] ?? null,
                'is_active' => true,
                'display_date' => today(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error parsing AI tip response: '.$e->getMessage());

            return $this->createSimpleTip($aiResponse, $category);
        }
    }

    /**
     * Create a simple tip when AI response parsing fails
     */
    protected function createSimpleTip(string $text, string $category): DailyTip
    {
        // Clean up the text and create a simple tip
        $cleanText = trim(strip_tags($text));
        if (strlen($cleanText) > 200) {
            $cleanText = substr($cleanText, 0, 197).'...';
        }

        return DailyTip::create([
            'tip' => $cleanText,
            'category' => $category,
            'technique' => null,
            'metadata' => null,
            'is_active' => true,
            'display_date' => today(),
        ]);
    }

    /**
     * Get a fallback tip when AI generation fails
     */
    protected function getFallbackTip(string $category): DailyTip
    {
        $fallbacks = [
            'anxiety' => 'Try the 5-4-3-2-1 grounding technique: Name 5 things you see, 4 you can touch, 3 you hear, 2 you smell, and 1 you taste.',
            'stress' => 'Take three deep breaths: inhale for 4 counts, hold for 4, exhale for 6. This activates your relaxation response.',
            'mood' => 'Write down three things you\'re grateful for today. Gratitude practice can significantly improve your mood.',
            'sleep' => 'Create a bedtime routine: dim lights, put away screens, and do something calming 30 minutes before bed.',
            'mindfulness' => 'Spend 2 minutes focusing on your breath. When your mind wanders, gently bring it back to your breathing.',
            'self-care' => 'Be kind to yourself today. Speak to yourself as you would to a good friend going through a tough time.',
        ];

        $tipText = $fallbacks[$category] ?? $fallbacks['anxiety'];

        return DailyTip::create([
            'tip' => $tipText,
            'category' => $category,
            'technique' => 'grounding',
            'metadata' => null,
            'is_active' => true,
            'display_date' => today(),
        ]);
    }

    /**
     * Get random category for tip generation
     */
    protected function getRandomCategory(): string
    {
        $categories = ['anxiety', 'stress', 'mood', 'sleep', 'mindfulness', 'self-care'];

        return $categories[array_rand($categories)];
    }

    /**
     * Get tips by category
     */
    public function getTipsByCategory(string $category, int $limit = 10): array
    {
        return DailyTip::active()
            ->where('category', $category)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Regenerate tip for today (admin function)
     */
    public function regenerateTodayTip(): ?DailyTip
    {
        // Deactivate current tip
        DailyTip::forToday()->update(['is_active' => false]);

        // Generate new tip
        return $this->generateNewTip();
    }
}
