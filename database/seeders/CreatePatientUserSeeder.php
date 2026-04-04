<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CreatePatientUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->info('Creating a patient user for testing...');

        // Find the patient role
        $patientRole = Role::where('name', 'patient')->first();

        if (! $patientRole) {
            $this->info('Patient role not found, creating it...');
            $patientRole = Role::create([
                'name' => 'patient',
                'display_name' => 'Patient',
                'description' => 'Patient user role',
            ]);
        }

        // Check if we already have a patient user
        $patientUser = User::where('role_id', $patientRole->id)->first();

        if (! $patientUser) {
            $this->info('Creating a new patient user...');
            $patientUser = User::withoutEvents(fn () => User::create([
                'first_name' => 'Test',
                'last_name' => 'Patient',
                'email' => 'test.patient@onwynd.com',
                'password' => Hash::make('password'),
                'role_id' => $patientRole->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]));

            $this->info("Created patient user: {$patientUser->email}");
        } else {
            $this->info("Found existing patient user: {$patientUser->email}");
        }

        // Create patient profile if it doesn't exist
        if (! $patientUser->patient) {
            $this->info('Creating patient profile...');

            Patient::create([
                'user_id' => $patientUser->id,
                'medical_history' => json_encode([]),
                'current_medications' => json_encode([]),
                'allergies' => json_encode([]),
                'emergency_contact_name' => 'Emergency Contact',
                'emergency_contact_phone' => '+1234567890',
                'emergency_contact_relationship' => 'family',
            ]);

            $this->info("Patient profile created for user {$patientUser->id}");
        } else {
            $this->info("Patient profile already exists for user {$patientUser->id}");
        }

        $this->info('Patient user setup completed!');
        $this->info("Email: {$patientUser->email}");
        $this->info('Password: password');
    }

    private function info(string $message): void
    {
        echo $message.PHP_EOL;
    }
}
