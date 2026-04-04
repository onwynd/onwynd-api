<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\TherapistAvailability;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GroupTherapistSeeder extends Seeder
{
    public function run(): void
    {
        $therapistRole = Role::where('slug', 'therapist')->first();
        if (! $therapistRole) {
            $this->command->warn('Therapist role not found. Run RoleSeeder first.');

            return;
        }

        // day_of_week is stored as an integer: 0=Sunday, 1=Monday … 6=Saturday
        $dayMap = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        // Images for group therapy specialists
        $images = [
            'therapists/group_therapist_1.png',
            'therapists/group_therapist_2.png',
            'therapists/group_therapist_3.png',
            'therapists/group_therapist_4.png',
        ];

        /**
         * Each therapist has a unique schedule so the booking calendar and
         * time-slot picker clearly demonstrate the availability feature:
         *
         *  Dr. Amina  – evening specialist (Mon / Wed / Fri evenings + Sat morning)
         *  Chinwe     – family/women focus  (Tue / Thu afternoons + Sat/Sun mornings)
         *  Dr. Tunde  – corporate wellness  (Mon / Tue / Thu early morning + Sat afternoon)
         *  Grace      – mixed / weekend     (Wed / Fri evenings + Sat midday + Sun afternoon)
         *
         * Each window is ≥ 3 hours so it generates multiple bookable slots
         * regardless of whether the patient picks 30 / 45 / 60 / 90 min sessions.
         */
        $groupTherapists = [
            [
                'first_name' => 'Dr. Amina',
                'last_name' => 'Bello',
                'email' => 'amina.bello@onwynd.com',
                'bio' => 'Dr. Amina Bello is a certified group therapy facilitator with 15 years of experience leading support groups for anxiety, depression, and trauma recovery. She specializes in creating safe, inclusive spaces where participants can share experiences and learn from collective wisdom.',
                'specializations' => ['Group Therapy', 'Support Groups', 'Anxiety Management', 'Trauma Recovery', 'Community Healing'],
                'qualifications' => ['PhD Clinical Psychology – Ahmadu Bello University', 'Certified Group Psychotherapist (CGP)', 'Advanced Group Facilitation Certificate', 'Trauma-Informed Group Therapy Training'],
                'languages' => ['English', 'Hausa', 'Fulani'],
                'experience_years' => 15,
                'hourly_rate' => 30000,
                'currency' => 'NGN',
                'license_number' => 'LCP-NG-20160H',
                'rating_average' => 4.8,
                'total_sessions' => 650,
                'image' => $images[0],
                // Mon / Wed / Fri evenings + Saturday morning
                'availability' => [
                    ['day' => 'monday',    'start' => '17:00', 'end' => '21:00'],
                    ['day' => 'wednesday', 'start' => '17:00', 'end' => '21:00'],
                    ['day' => 'friday',    'start' => '17:00', 'end' => '21:00'],
                    ['day' => 'saturday',  'start' => '09:00', 'end' => '13:00'],
                ],
            ],
            [
                'first_name' => 'Chinwe',
                'last_name' => 'Obi',
                'email' => 'chinwe.obi@onwynd.com',
                'bio' => 'Chinwe Obi is an experienced group counselor who facilitates healing circles and support groups for women, families, and adolescents. Her approach combines traditional African healing practices with modern group therapy techniques.',
                'specializations' => ["Women's Support Groups", 'Family Therapy Groups', 'Adolescent Groups', 'Cultural Healing Circles', 'Grief Support'],
                'qualifications' => ['MSc Counseling Psychology – University of Nigeria', 'Certified Group Counselor', 'African Traditional Healing Practitioner', 'Family Systems Therapy Training'],
                'languages' => ['English', 'Igbo', 'Yoruba'],
                'experience_years' => 11,
                'hourly_rate' => 25000,
                'currency' => 'NGN',
                'license_number' => 'CP-NG-20190I',
                'rating_average' => 4.7,
                'total_sessions' => 480,
                'image' => $images[1],
                // Tue / Thu afternoons + Sat / Sun mornings
                'availability' => [
                    ['day' => 'tuesday',  'start' => '13:00', 'end' => '18:00'],
                    ['day' => 'thursday', 'start' => '13:00', 'end' => '18:00'],
                    ['day' => 'saturday', 'start' => '08:00', 'end' => '12:00'],
                    ['day' => 'sunday',   'start' => '10:00', 'end' => '14:00'],
                ],
            ],
            [
                'first_name' => 'Dr. Tunde',
                'last_name' => 'Adebayo',
                'email' => 'tunde.adebayo@onwynd.com',
                'bio' => "Dr. Tunde Adebayo specializes in men's mental health groups and corporate wellness programs. He leads therapeutic groups focused on stress management, work-life balance, and building emotional intelligence in professional settings.",
                'specializations' => ["Men's Groups", 'Corporate Wellness Groups', 'Stress Management', 'Work-Life Balance', 'Emotional Intelligence Training'],
                'qualifications' => ['PhD Organizational Psychology – University of Lagos', 'Certified Group Psychotherapist', 'Corporate Wellness Specialist', 'EMDR Group Therapy Certification'],
                'languages' => ['English', 'Yoruba', 'Pidgin'],
                'experience_years' => 13,
                'hourly_rate' => 28000,
                'currency' => 'NGN',
                'license_number' => 'LCP-NG-20170J',
                'rating_average' => 4.6,
                'total_sessions' => 520,
                'image' => $images[2],
                // Mon / Tue / Thu early mornings (before work) + Saturday afternoon
                'availability' => [
                    ['day' => 'monday',    'start' => '06:00', 'end' => '10:00'],
                    ['day' => 'tuesday',   'start' => '06:00', 'end' => '10:00'],
                    ['day' => 'thursday',  'start' => '06:00', 'end' => '10:00'],
                    ['day' => 'saturday',  'start' => '13:00', 'end' => '18:00'],
                ],
            ],
            [
                'first_name' => 'Grace',
                'last_name' => 'Johnson',
                'email' => 'grace.johnson@onwynd.com',
                'bio' => 'Grace Johnson is a multilingual group therapist who facilitates culturally diverse support groups. She specializes in trauma-informed group work and creates inclusive spaces for people from different cultural backgrounds to heal together.',
                'specializations' => ['Multicultural Groups', 'Trauma-Informed Groups', 'Community Building', 'Cross-Cultural Therapy', 'Social Justice Groups'],
                'qualifications' => ['MSc Social Work – University of Ibadan', 'Licensed Clinical Social Worker', 'Cultural Competency Training', 'Trauma-Informed Care Specialist'],
                'languages' => ['English', 'Pidgin', 'Hausa', 'Yoruba'],
                'experience_years' => 9,
                'hourly_rate' => 22000,
                'currency' => 'NGN',
                'license_number' => 'LCSW-NG-20200K',
                'rating_average' => 4.9,
                'total_sessions' => 380,
                'image' => $images[3],
                // Wed / Fri evenings + Sat midday + Sun afternoon
                'availability' => [
                    ['day' => 'wednesday', 'start' => '16:00', 'end' => '20:00'],
                    ['day' => 'friday',    'start' => '16:00', 'end' => '20:00'],
                    ['day' => 'saturday',  'start' => '11:00', 'end' => '15:00'],
                    ['day' => 'sunday',    'start' => '14:00', 'end' => '18:00'],
                ],
            ],
        ];

        foreach ($groupTherapists as $data) {
            // Create or update the user account
            $user = User::withoutEvents(fn () => User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'password' => Hash::make('Onwynd@2025!'),
                    'role_id' => $therapistRole->id,
                    'uuid' => Str::uuid(),
                    'email_verified_at' => now(),
                    'profile_photo' => $data['image'],
                    'is_active' => true,
                ]
            ));

            // Create or update the therapist profile
            TherapistProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'bio' => $data['bio'],
                    'specializations' => $data['specializations'],
                    'qualifications' => $data['qualifications'],
                    'languages' => $data['languages'],
                    'experience_years' => $data['experience_years'],
                    'hourly_rate' => $data['hourly_rate'],
                    'currency' => $data['currency'],
                    'license_number' => $data['license_number'],
                    'license_state' => 'Federal Republic of Nigeria',
                    'license_expiry' => now()->addYears(2)->format('Y-m-d'),
                    'status' => 'approved',
                    'is_verified' => true,
                    'is_accepting_clients' => true,
                    'rating_average' => $data['rating_average'],
                    'total_sessions' => $data['total_sessions'],
                ]
            );

            // Replace availability with this therapist's unique schedule
            TherapistAvailability::where('therapist_id', $user->id)->delete();
            foreach ($data['availability'] as $slot) {
                TherapistAvailability::create([
                    'therapist_id' => $user->id,
                    'day_of_week' => $dayMap[$slot['day']] ?? 0,
                    'start_time' => $slot['start'],
                    'end_time' => $slot['end'],
                    'is_recurring' => true,
                    'specific_date' => null,
                    'is_available' => true,
                ]);
            }

            $this->command->info("Seeded group therapy specialist: {$data['first_name']} {$data['last_name']}");
        }
    }
}
