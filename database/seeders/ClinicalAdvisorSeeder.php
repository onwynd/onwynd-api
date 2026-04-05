<?php

namespace Database\Seeders;

use App\Models\ClinicalAdvisor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ClinicalAdvisorSeeder extends Seeder
{
    /**
     * Seed the ClinicalAdvisor profile for the seeded clinical_advisor user.
     *
     * Safe to run multiple times — uses firstOrCreate on user_id.
     * Run standalone: php artisan db:seed --class=ClinicalAdvisorSeeder
     */
    public function run(): void
    {
        $user = User::where('email', 'clinical_advisor@onwynd.com')->first();

        if (! $user) {
            $this->command->warn('clinical_advisor@onwynd.com not found — run UserSeeder first.');
            return;
        }

        // Use DB insert to avoid model cast double-encoding on plain string fields.
        // The timezone column is json_valid-constrained so we encode it explicitly.
        $exists = ClinicalAdvisor::where('user_id', $user->id)->exists();

        if (! $exists) {
            DB::table('clinical_advisors')->insert([
                'id'                  => (string) Str::uuid(),
                'user_id'             => $user->id,
                'license_number'      => 'CA-SEED-001',
                'credential_type'     => 'clinical_psychologist',
                'license_expiry_date' => now()->addYears(3),
                'specializations'     => json_encode(['anxiety', 'depression', 'trauma']),
                'languages'           => json_encode(['english']),
                'working_hours'       => json_encode([
                    'monday'    => ['start' => '09:00', 'end' => '17:00'],
                    'tuesday'   => ['start' => '09:00', 'end' => '17:00'],
                    'wednesday' => ['start' => '09:00', 'end' => '17:00'],
                    'thursday'  => ['start' => '09:00', 'end' => '17:00'],
                    'friday'    => ['start' => '09:00', 'end' => '17:00'],
                ]),
                'timezone'              => json_encode('Africa/Lagos'),
                'max_reviews_per_day'   => 20,
                'phone_number_primary'  => '+2340000000001',
                'email_primary'         => 'clinical_advisor@onwynd.com',
                'verification_status'   => 'verified',
                'verified_at'           => now(),
                'status'                => 'active',
                'training_completed'    => json_encode(['crisis_intervention', 'cultural_competency', 'hipaa']),
                'last_training_date'    => now()->subMonths(3),
                'enable_sms_alerts'     => true,
                'enable_push_alerts'    => true,
                'enable_email_digest'   => true,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }

        $this->command->info('ClinicalAdvisor profile seeded for clinical_advisor@onwynd.com');
    }
}
