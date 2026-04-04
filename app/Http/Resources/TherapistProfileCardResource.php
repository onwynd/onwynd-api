<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Patient-facing therapist matching card.
 * Wraps the Therapist model (therapist_profiles table).
 */
class TherapistProfileCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;
        $userCurrency = $request->user()?->currency ?? 'NGN';

        return [
            'id'                  => $this->id,
            'user_id'             => $this->user_id,
            'display_name'        => $user
                ? ($user->display_name ?: $user->first_name)
                : 'Therapist',
            'avatar_url'          => $user?->profile_photo_url,
            'bio'                 => $this->bio,
            'specializations'     => $this->specializations ?? [],
            'languages'           => $this->languages ?? [],
            'cultural_competencies' => $this->cultural_competencies ?? [],
            'experience_years'    => $this->experience_years,
            'rating_average'      => $this->rating_average,
            'total_sessions'      => $this->total_sessions,
            'rate_display'        => $this->formatRate($userCurrency),
            'has_35min_slot'      => (bool) $this->has_35min_slot,
            'is_accepting_clients' => (bool) $this->is_accepting_clients,
            'country_of_operation' => $this->country_of_operation,
            'timezone'            => $this->timezone,
            'match_type'          => $this->match_type ?? null,
            'is_expanded_result'  => $this->is_expanded_result ?? false,
            'match_badge'         => $this->getMatchBadge(),
        ];
    }

    private function formatRate(string $currency): string
    {
        if ($currency === 'USD' && $this->payout_currency === 'USD') {
            $rate = $this->hourly_rate ?? 0;
            return '$' . number_format($rate, 0) . '/hr';
        }

        $rate = $this->hourly_rate ?? 0;
        return '₦' . number_format($rate, 0) . '/hr';
    }

    private function getMatchBadge(): ?string
    {
        $matchType = $this->match_type ?? null;

        return match ($matchType) {
            'regional'  => 'Available in your region',
            'language'  => 'Speaks your language',
            'all'       => null,
            default     => null,
        };
    }
}
