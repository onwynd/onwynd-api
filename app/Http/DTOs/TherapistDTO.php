<?php

namespace App\Http\DTOs;

use App\Models\Therapist;

class TherapistDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $fullName,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly ?string $bio,
        public readonly ?array $specializations,
        public readonly ?array $qualifications,
        public readonly ?int $yearsOfExperience,
        public readonly ?string $licenseNumber,
        public readonly ?float $hourlyRate,
        public readonly ?string $currency,
        public readonly ?string $status,
        public readonly ?string $avatarUrl,
        public readonly ?string $certificateUrl,
        public readonly ?array $languages,
        public readonly ?array $areasOfFocus,
        public readonly ?array $verification,
        public readonly ?array $stats,
        public readonly string $createdAt
    ) {}

    public static function fromModel(Therapist $therapist): self
    {
        $user = $therapist->user;

        return new self(
            id: $user->id,
            fullName: $user->name,
            email: $user->email,
            phone: $user->phone,
            bio: $therapist->bio,
            specializations: $therapist->specializations,
            qualifications: $therapist->qualifications,
            yearsOfExperience: $therapist->experience_years,
            licenseNumber: $therapist->license_number,
            hourlyRate: (float) $therapist->hourly_rate,
            currency: $therapist->currency,
            status: $therapist->status,
            avatarUrl: $user->profile_photo_url,
            certificateUrl: $therapist->certificate_url, // Assuming this is a derived attribute
            languages: $therapist->languages,
            areasOfFocus: $therapist->areas_of_focus, // Assuming this is a derived attribute
            verification: [ // Assuming this is a derived attribute
                'status' => $therapist->verification_status,
                'is_verified' => $therapist->is_verified,
                'rejection_reason' => $therapist->rejection_reason,
                'rejected_at' => $therapist->rejected_at,
                'verified_at' => $therapist->verified_at,
                'has_certificate' => !empty($therapist->certificate_url),
            ],
            stats: [ // Assuming this is a derived attribute
                'total_sessions' => $therapist->total_sessions,
                'completed_sessions' => $therapist->completed_sessions,
                'average_rating' => (float) $therapist->rating_average,
                'total_reviews' => $therapist->reviews_count,
            ],
            createdAt: $user->created_at->toIso8601String()
        );
    }
}
