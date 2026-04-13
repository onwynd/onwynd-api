<?php

namespace App\Http\DTOs;

use App\Models\User;

class UserDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $role,
        public readonly ?string $profilePhoto,
        public readonly bool $isActive,
        public readonly ?string $currentPlan,
        public readonly ?string $currentPlanSlug,
        public readonly ?string $subscriptionStatus,
        public readonly ?bool $compedUpgradeApproved,
        public readonly ?string $compedApprovedBy
    ) {}

    public static function fromModel(User $user): self
    {
        $subscription = $user->activeSubscription();

        return new self(
            id: $user->id,
            uuid: $user->uuid,
            firstName: $user->first_name,
            lastName: $user->last_name,
            name: $user->name,
            email: $user->email,
            role: $user->role?->slug,
            profilePhoto: $user->profile_photo_url,
            isActive: $user->is_active,
            currentPlan: $subscription?->plan?->name,
            currentPlanSlug: $subscription?->plan?->slug,
            subscriptionStatus: $subscription?->status,
            compedUpgradeApproved: $subscription?->comped_upgrade_approved,
            compedApprovedBy: $subscription?->compedApprovedBy?->name
        );
    }
}
