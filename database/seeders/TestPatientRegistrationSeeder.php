<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestPatientRegistrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->info('Testing patient registration with automatic profile creation...');

        // Find the patient role
        $patientRole = Role::where('slug', 'patient')->first();

        if (! $patientRole) {
            $this->error('Patient role not found!');

            return;
        }

        // Create a new user with patient role (simulating registration)
        $this->info('Creating test patient user...');
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'email' => 'test.auto.patient@onwynd.com',
            'password' => Hash::make('password'),
            'role_id' => $patientRole->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'gender' => 'prefer_not_to_say',
        ]);

        $this->info("Created user: {$user->email} with role: {$patientRole->name}");

        // Trigger the UserCreated event (this should automatically create patient profile)
        event(new \App\Events\UserCreated($user));

        // Check if patient profile was created
        $user->refresh();
        if ($user->patient) {
            $this->info('✅ SUCCESS: Patient profile automatically created!');
            $this->info("Patient ID: {$user->patient->id}");
            $this->info("User ID: {$user->id}");
        } else {
            $this->error('❌ FAILED: Patient profile was not created automatically!');
        }

        $this->info('Test completed!');
    }

    private function info(string $message): void
    {
        echo "ℹ️  {$message}".PHP_EOL;
    }

    private function error(string $message): void
    {
        echo "❌ {$message}".PHP_EOL;
    }
}
