<?php

namespace App\Console\Commands;

use App\Models\Therapist;
use Illuminate\Console\Command;

class CreateTherapistProfile extends Command
{
    protected $signature = 'therapist:create-profile {user_id}';

    protected $description = 'Create a minimal therapist profile for a given user_id';

    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');

        $profile = Therapist::create([
            'user_id' => $userId,
            'license_number' => 'TEST-'.uniqid(),
            'license_state' => 'NG',
            'license_expiry' => now()->addYear()->toDateString(),
            'specializations' => ['general'],
            'qualifications' => [['degree' => 'BSc Psychology', 'institution' => 'Test Univ', 'year' => 2020]],
            'languages' => ['en'],
            'experience_years' => 3,
            'hourly_rate' => 15000,
            'currency' => 'NGN',
            'bio' => 'Test therapist profile',
            'status' => 'active',
            'is_verified' => true,
            'verified_at' => now(),
            'is_accepting_clients' => true,
            'verification_documents' => [],
            'rating_average' => 4.5,
            'total_sessions' => 0,
        ]);

        $this->line(json_encode(['id' => $profile->id]));

        return self::SUCCESS;
    }
}
