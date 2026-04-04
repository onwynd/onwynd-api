<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Models\Patient;
use App\Models\Role;
use Illuminate\Support\Facades\Log;

class CreatePatientProfile
{
    /**
     * Handle the event
     */
    public function handle(UserCreated $event): void
    {
        try {
            $user = $event->user;

            Log::info('Checking if patient profile should be created', ['user_id' => $user->id, 'role_id' => $user->role_id]);

            // Check if user has patient role
            $patientRole = Role::where('slug', 'patient')->first();

            if (! $patientRole) {
                Log::warning('Patient role not found, skipping patient profile creation');

                return;
            }

            if ($user->role_id === $patientRole->id) {
                Log::info('Creating patient profile for user', ['user_id' => $user->id]);

                // Create patient profile (idempotent — safe if event fires more than once)
                $patient = Patient::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'medical_history' => json_encode([]),
                        'current_medications' => json_encode([]),
                        'allergies' => json_encode([]),
                        'emergency_contact_name' => null,
                        'emergency_contact_phone' => null,
                        'emergency_contact_relationship' => null,
                    ]
                );

                if ($patient->wasRecentlyCreated) {
                    Log::info('Patient profile created successfully', ['user_id' => $user->id, 'patient_id' => $patient->id]);
                } else {
                    Log::info('Patient profile already exists', ['user_id' => $user->id, 'patient_id' => $patient->id]);
                }
            } else {
                Log::info('User is not a patient, skipping patient profile creation', ['user_id' => $user->id, 'role_id' => $user->role_id]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to create patient profile', [
                'user_id' => $user->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
