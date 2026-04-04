<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CheckUserPatientProfilesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->info('Checking user roles and patient profiles...');

        // Get all users with their roles and patient status
        $users = DB::table('users')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->leftJoin('patients', 'users.id', '=', 'patients.user_id')
            ->select('users.id', 'users.email', 'users.role_id', 'roles.name as role_name', 'patients.id as patient_id')
            ->orderBy('users.id', 'desc')
            ->limit(10)
            ->get();

        foreach ($users as $user) {
            $hasPatient = $user->patient_id ? 'Yes' : 'No';
            $this->info("User ID: {$user->id}, Email: {$user->email}, Role: {$user->role_name}, Has Patient Profile: {$hasPatient}");

            // If user is a patient but doesn't have a patient profile, create one
            if ($user->role_name === 'patient' && ! $user->patient_id) {
                $this->info("Creating patient profile for user {$user->id}...");

                Patient::create([
                    'user_id' => $user->id,
                    'medical_history' => json_encode([]),
                    'current_medications' => json_encode([]),
                    'allergies' => json_encode([]),
                    'emergency_contact_name' => 'Emergency Contact',
                    'emergency_contact_phone' => '+1234567890',
                    'emergency_contact_relationship' => 'family',
                ]);

                $this->info("Patient profile created for user {$user->id}");
            }
        }

        $this->info('Check completed!');
    }

    private function info(string $message): void
    {
        echo $message.PHP_EOL;
    }
}
