<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OnboardingController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'mental_health_goals' => $user->mental_health_goals ?? [],
                'preferences' => $user->preferences ?? [],
                'onboarding_step' => $user->onboarding_step ?? 0,
                'onboarding_completed_at' => $user->onboarding_completed_at,
                'privacy_consent_given_at' => $user->privacy_consent_given_at,
            ],
        ]);
    }

    public function firstLoginStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $requireBreathing = is_null($user->last_activity_at) && ((int) ($user->streak_count ?? 0) === 0);

        return response()->json([
            'require_breathing' => $requireBreathing,
            'minutes' => 1,
        ]);
    }

    public function firstLoginComplete(Request $request): JsonResponse
    {
        $user = $request->user();

        $duration = (int) $request->input('duration_seconds', 60);
        if ($duration >= 60) {
            $user->incrementStreak();
        }

        return response()->json([
            'streak_count' => (int) $user->streak_count,
            'last_activity_at' => $user->last_activity_at,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Accept `therapy_preferences` (frontend name) or `preferences` (legacy)
        $therapyPrefs = $request->input('therapy_preferences', $request->input('preferences', []));

        $normalized = [
            'mental_health_goals'  => $request->input('mental_health_goals', $request->input('goals', [])),
            'preferences'          => is_array($therapyPrefs) ? $therapyPrefs : [],
            'onboarding_step'      => $request->input('onboarding_step', $request->input('step')),
            'completed'            => $request->boolean('completed', $request->boolean('isComplete')),
            'privacy_consent'      => $request->boolean('privacy_consent', $request->boolean('consent')),
            'ai_tone_preference'   => $request->input('ai_tone_preference'),
            'timezone'             => $request->input('timezone'),
        ];

        $validator = Validator::make($normalized, [
            'mental_health_goals' => ['nullable', 'array'],
            'mental_health_goals.*' => ['string'],
            'preferences' => ['nullable', 'array'],
            'onboarding_step' => ['nullable', 'integer', 'min:0'],
            'completed' => ['nullable', 'boolean'],
            'privacy_consent' => ['nullable', 'boolean'],
            'ai_tone_preference' => ['nullable', 'string', 'in:warm_nurturing,clinical_professional,motivational,calm_meditative'],
            'timezone'           => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid onboarding payload', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (array_key_exists('mental_health_goals', $data)) {
            $user->mental_health_goals = $data['mental_health_goals'];
        }

        if (array_key_exists('preferences', $data)) {
            $user->preferences = $data['preferences'];
        }

        if (array_key_exists('onboarding_step', $data)) {
            $user->onboarding_step = $data['onboarding_step'];
        }

        if ((bool) ($data['completed'] ?? false)) {
            $user->onboarding_completed_at = now();
        }

        if ((bool) ($data['privacy_consent'] ?? false)) {
            $user->privacy_consent_given_at = now();
        }

        if (array_key_exists('ai_tone_preference', $data) && $data['ai_tone_preference'] !== null) {
            $user->ai_tone_preference = $data['ai_tone_preference'];
        }

        if (! empty($data['timezone'])) {
            $user->timezone = $data['timezone'];
        }

        $user->save();

        return response()->json([
            'message' => 'Onboarding data saved',
            'data' => [
                'mental_health_goals' => $user->mental_health_goals,
                'preferences' => $user->preferences,
                'onboarding_step' => $user->onboarding_step,
                'onboarding_completed_at' => $user->onboarding_completed_at,
                'privacy_consent_given_at' => $user->privacy_consent_given_at,
            ],
        ]);
    }
}
