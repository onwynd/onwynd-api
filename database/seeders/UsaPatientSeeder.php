<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * UsaPatientSeeder
 *
 * Seeds 17 real USA patient users (IDs 62–78).
 * AUTO_INCREMENT is forced to 62 before insertion so IDs are predictable.
 *
 * No emails are sent during seeding — welcome emails will be triggered
 * manually when the time is right.
 *
 * Run standalone: php artisan db:seed --class=UsaPatientSeeder
 */
class UsaPatientSeeder extends Seeder
{
    public function run(): void
    {
        $patientRole = Role::where('slug', 'patient')->first();

        if (! $patientRole) {
            $this->command->error('Role "patient" not found. Run RoleSeeder first.');
            return;
        }

        // Force the auto-increment to 62 so USA users get IDs 62–78.
        // All demo/seeder users must be inserted before this seeder runs.
        DB::statement('ALTER TABLE users AUTO_INCREMENT = 62');

        $users = [
            ['first_name' => 'James',      'last_name' => 'Mitchell',  'email' => 'james.mitchell@gmail.com',    'gender' => 'male',   'state' => 'California',   'city' => 'Los Angeles'],
            ['first_name' => 'Sarah',      'last_name' => 'Thompson',  'email' => 'sarah.thompson@gmail.com',    'gender' => 'female', 'state' => 'New York',      'city' => 'New York City'],
            ['first_name' => 'Marcus',     'last_name' => 'Williams',  'email' => 'marcus.williams@gmail.com',   'gender' => 'male',   'state' => 'Texas',         'city' => 'Houston'],
            ['first_name' => 'Emily',      'last_name' => 'Johnson',   'email' => 'emily.johnson@gmail.com',     'gender' => 'female', 'state' => 'Florida',       'city' => 'Miami'],
            ['first_name' => 'David',      'last_name' => 'Anderson',  'email' => 'david.anderson@gmail.com',    'gender' => 'male',   'state' => 'Illinois',      'city' => 'Chicago'],
            ['first_name' => 'Jessica',    'last_name' => 'Brown',     'email' => 'jessica.brown@gmail.com',     'gender' => 'female', 'state' => 'Georgia',       'city' => 'Atlanta'],
            ['first_name' => 'Michael',    'last_name' => 'Davis',     'email' => 'michael.davis@gmail.com',     'gender' => 'male',   'state' => 'Washington',    'city' => 'Seattle'],
            ['first_name' => 'Ashley',     'last_name' => 'Wilson',    'email' => 'ashley.wilson@gmail.com',     'gender' => 'female', 'state' => 'Arizona',       'city' => 'Phoenix'],
            ['first_name' => 'Christopher','last_name' => 'Martinez',  'email' => 'chris.martinez@gmail.com',    'gender' => 'male',   'state' => 'Colorado',      'city' => 'Denver'],
            ['first_name' => 'Stephanie',  'last_name' => 'Taylor',    'email' => 'stephanie.taylor@gmail.com',  'gender' => 'female', 'state' => 'North Carolina','city' => 'Charlotte'],
            ['first_name' => 'Joshua',     'last_name' => 'Lee',       'email' => 'joshua.lee@gmail.com',        'gender' => 'male',   'state' => 'Ohio',          'city' => 'Columbus'],
            ['first_name' => 'Amanda',     'last_name' => 'Harris',    'email' => 'amanda.harris@gmail.com',     'gender' => 'female', 'state' => 'Michigan',      'city' => 'Detroit'],
            ['first_name' => 'Daniel',     'last_name' => 'Clark',     'email' => 'daniel.clark@gmail.com',      'gender' => 'male',   'state' => 'Pennsylvania',  'city' => 'Philadelphia'],
            ['first_name' => 'Megan',      'last_name' => 'Lewis',     'email' => 'megan.lewis@gmail.com',       'gender' => 'female', 'state' => 'Massachusetts', 'city' => 'Boston'],
            ['first_name' => 'Ryan',       'last_name' => 'Walker',    'email' => 'ryan.walker@gmail.com',       'gender' => 'male',   'state' => 'Minnesota',     'city' => 'Minneapolis'],
            ['first_name' => 'Lauren',     'last_name' => 'Hall',      'email' => 'lauren.hall@gmail.com',       'gender' => 'female', 'state' => 'Oregon',        'city' => 'Portland'],
            ['first_name' => 'Kevin',      'last_name' => 'Young',     'email' => 'kevin.young@gmail.com',       'gender' => 'male',   'state' => 'Nevada',        'city' => 'Las Vegas'],
        ];

        foreach ($users as $data) {
            if (User::where('email', $data['email'])->exists()) {
                $this->command->warn("Skipping {$data['email']} — already exists.");
                continue;
            }

            $user = User::withoutEvents(fn () => User::create([
                'uuid'              => (string) Str::uuid(),
                'role_id'           => $patientRole->id,
                'first_name'        => $data['first_name'],
                'last_name'         => $data['last_name'],
                'email'             => $data['email'],
                'password'          => Hash::make('Onwynd@2025!'),
                'gender'            => $data['gender'],
                'country'           => 'United States',
                'state'             => $data['state'],
                'city'              => $data['city'],
                'timezone'          => 'America/New_York',
                'language'          => 'en',
                'is_active'         => true,
                'email_verified_at' => now(),
            ]));

            $this->command->info("Seeded USA user: {$data['first_name']} {$data['last_name']} <{$data['email']}> (ID: {$user->id})");
        }

        $this->command->info('UsaPatientSeeder complete — ' . count($users) . ' USA patients (IDs 62–78). No emails sent.');
    }
}
