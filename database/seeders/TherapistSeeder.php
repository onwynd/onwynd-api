<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\TherapistAvailability;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TherapistSeeder extends Seeder
{
    public function run(): void
    {
        $therapistRole = Role::where('slug', 'therapist')->first();
        if (! $therapistRole) {
            $this->command->warn('Therapist role not found. Run RoleSeeder first.');

            return;
        }

        // 7 images available in storage/app/public/therapists/
        // Rename files to URL-safe names if originals still exist
        $renames = [
            'therapists/therapist (1).jpg' => 'therapists/therapist-1.jpg',
            'therapists/therapist (1).png' => 'therapists/therapist-1.png',
            'therapists/therapist (2).jpg' => 'therapists/therapist-2.jpg',
            'therapists/therapist (2).png' => 'therapists/therapist-2.png',
            'therapists/therapist (3).png' => 'therapists/therapist-3.png',
            'therapists/therapist (4).png' => 'therapists/therapist-4.png',
            'therapists/therapist (5).png' => 'therapists/therapist-5.png',
        ];
        foreach ($renames as $old => $new) {
            if (\Storage::disk('public')->exists($old) && ! \Storage::disk('public')->exists($new)) {
                \Storage::disk('public')->move($old, $new);
            }
        }

        $images = [
            'therapists/therapist-1.jpg',
            'therapists/therapist-1.png',
            'therapists/therapist-2.jpg',
            'therapists/therapist-2.png',
            'therapists/therapist-3.png',
            'therapists/therapist-4.png',
            'therapists/therapist-5.png',
        ];

        $therapists = [
            [
                'first_name' => 'Dr. Amara',
                'last_name' => 'Okafor',
                'email' => 'amara.okafor@onwynd.com',
                'bio' => 'Dr. Amara Okafor is a licensed clinical psychologist with over 12 years of experience specialising in anxiety, depression, and trauma. She integrates Cognitive Behavioural Therapy (CBT) with mindfulness-based approaches to help clients build lasting resilience.',
                'specializations' => ['Anxiety Disorders', 'Depression', 'Trauma & PTSD', 'Mindfulness-Based Therapy'],
                'qualifications' => ['PhD Clinical Psychology – University of Lagos', 'Licensed Clinical Psychologist (LCP)', 'Certified CBT Practitioner', 'Trauma-Informed Care Certificate'],
                'languages' => ['English', 'Yoruba'],
                'experience_years' => 12,
                'hourly_rate' => 25000,
                'currency' => 'NGN',
                'license_number' => 'LCP-NG-20190A',
                'rating_average' => 4.9,
                'total_sessions' => 534,
                'image' => $images[0],
            ],
            [
                'first_name' => 'Marcus',
                'last_name' => 'Adeleke',
                'email' => 'marcus.adeleke@onwynd.com',
                'bio' => 'Marcus Adeleke is a relationship and family therapist with a warm, empathetic approach. He helps individuals and couples navigate conflict, improve communication, and rebuild intimacy using Emotionally Focused Therapy (EFT).',
                'specializations' => ['Relationship Counselling', 'Family Therapy', 'Couples Therapy', 'Communication Skills'],
                'qualifications' => ['MSc Counselling Psychology – UI', 'Certified EFT Therapist', 'Gottman Method Level 2'],
                'languages' => ['English', 'Igbo'],
                'experience_years' => 8,
                'hourly_rate' => 18000,
                'currency' => 'NGN',
                'license_number' => 'CP-NG-20211B',
                'rating_average' => 4.8,
                'total_sessions' => 312,
                'image' => $images[1],
            ],
            [
                'first_name' => 'Dr. Chidinma',
                'last_name' => 'Eze',
                'email' => 'chidinma.eze@onwynd.com',
                'bio' => 'Dr. Chidinma Eze specialises in adolescent and young adult mental health. She is passionate about helping young people navigate identity, academic pressure, and social challenges using a strength-based, culturally sensitive framework.',
                'specializations' => ['Adolescent Mental Health', 'Young Adult Issues', 'Academic Stress', 'Identity & Self-Esteem'],
                'qualifications' => ['PhD Adolescent Psychology – UNILAG', 'Certified Adolescent Therapist', 'Positive Psychology Practitioner'],
                'languages' => ['English', 'Igbo', 'French'],
                'experience_years' => 9,
                'hourly_rate' => 22000,
                'currency' => 'NGN',
                'license_number' => 'LCP-NG-20180C',
                'rating_average' => 4.7,
                'total_sessions' => 421,
                'image' => $images[2],
            ],
            [
                'first_name' => 'Seun',
                'last_name' => 'Balogun',
                'email' => 'seun.balogun@onwynd.com',
                'bio' => 'Seun Balogun is a burnout and career stress specialist with a background in organisational psychology. He combines psychodynamic insights with practical coaching techniques to help professionals reclaim their wellbeing and career satisfaction.',
                'specializations' => ['Burnout & Work Stress', 'Career Transitions', 'Performance Anxiety', 'Organisational Wellbeing'],
                'qualifications' => ['MSc Organisational Psychology – Lagos Business School', 'CBT Practitioner', 'Certified Executive Coach'],
                'languages' => ['English', 'Yoruba'],
                'experience_years' => 6,
                'hourly_rate' => 20000,
                'currency' => 'NGN',
                'license_number' => 'CP-NG-20220D',
                'rating_average' => 4.6,
                'total_sessions' => 198,
                'image' => $images[3],
            ],
            [
                'first_name' => 'Dr. Fatima',
                'last_name' => 'Aliyu',
                'email' => 'fatima.aliyu@onwynd.com',
                'bio' => 'Dr. Fatima Aliyu is a grief and loss counsellor and bereavement specialist. With deep compassion and clinical expertise, she supports individuals through loss, life transitions, and existential challenges using a person-centred and meaning-making approach.',
                'specializations' => ['Grief & Bereavement', 'Loss & Life Transitions', 'Existential Therapy', 'Women\'s Mental Health'],
                'qualifications' => ['PhD Counselling – ABU Zaria', 'Certified Grief Counsellor', 'Person-Centred Therapy Training'],
                'languages' => ['English', 'Hausa', 'Fulani'],
                'experience_years' => 14,
                'hourly_rate' => 27000,
                'currency' => 'NGN',
                'license_number' => 'LCP-NG-20170E',
                'rating_average' => 5.0,
                'total_sessions' => 703,
                'image' => $images[4],
            ],
            [
                'first_name' => 'Emeka',
                'last_name' => 'Nwosu',
                'email' => 'emeka.nwosu@onwynd.com',
                'bio' => 'Emeka Nwosu is a men\'s mental health advocate and therapist. He creates a safe, non-judgmental space for men to explore vulnerability, emotional regulation, and relationship challenges, challenging the cultural stigma around seeking help.',
                'specializations' => ['Men\'s Mental Health', 'Anger Management', 'Emotional Regulation', 'Depression & Masculinity'],
                'qualifications' => ['BSc Psychology – OAU', 'MSc Psychotherapy – UNILAG', 'DBT Skills Training Certificate'],
                'languages' => ['English', 'Igbo'],
                'experience_years' => 5,
                'hourly_rate' => 15000,
                'currency' => 'NGN',
                'license_number' => 'CP-NG-20230F',
                'rating_average' => 4.5,
                'total_sessions' => 143,
                'image' => $images[5],
            ],
            [
                'first_name' => 'Dr. Ngozi',
                'last_name' => 'Okonkwo',
                'email' => 'ngozi.okonkwo@onwynd.com',
                'bio' => 'Dr. Ngozi Okonkwo is a neuropsychologist and holistic wellness therapist. She brings together neuroscience, somatic therapy, and mindfulness to address ADHD, chronic stress, and complex trauma, empowering clients to understand and heal their minds.',
                'specializations' => ['ADHD & Executive Function', 'Complex Trauma', 'Somatic Therapy', 'Chronic Stress & Nervous System'],
                'qualifications' => ['PhD Neuropsychology – UCH Ibadan', 'Somatic Experiencing Practitioner', 'EMDR Certified Therapist'],
                'languages' => ['English', 'Igbo'],
                'experience_years' => 16,
                'hourly_rate' => 35000,
                'currency' => 'NGN',
                'license_number' => 'LCP-NG-20150G',
                'rating_average' => 4.9,
                'total_sessions' => 892,
                'image' => $images[6],
            ],
        ];

        // Availability slots: Mon–Fri with morning and afternoon windows
        // controller and database expect an integer 0‑6 (Sun=0), so we'll map names below.
        $weekdaySlots = [
            ['day' => 'monday',    'start' => '09:00', 'end' => '12:00'],
            ['day' => 'monday',    'start' => '14:00', 'end' => '17:00'],
            ['day' => 'tuesday',   'start' => '09:00', 'end' => '12:00'],
            ['day' => 'tuesday',   'start' => '14:00', 'end' => '17:00'],
            ['day' => 'wednesday', 'start' => '10:00', 'end' => '13:00'],
            ['day' => 'wednesday', 'start' => '15:00', 'end' => '18:00'],
            ['day' => 'thursday',  'start' => '09:00', 'end' => '12:00'],
            ['day' => 'thursday',  'start' => '14:00', 'end' => '17:00'],
            ['day' => 'friday',    'start' => '09:00', 'end' => '13:00'],
        ];
        $dayMap = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        foreach ($therapists as $data) {
            // Create or update the user account
            $user = User::withoutEvents(fn () => User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'password' => Hash::make('Onwynd@2025!'),
                    'role_id' => $therapistRole->id,
                    'uuid' => \DB::table('users')->where('email', $data['email'])->value('uuid') ?? (string) Str::uuid(),
                    'email_verified_at' => now(),
                    'profile_photo' => $data['image'],
                    'is_active' => true,
                ]
            ));

            // Create or update the therapist profile (TherapistProfile = patient-facing therapist_profiles table)
            $therapist = TherapistProfile::updateOrCreate(
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

            // Seed availability (delete old, recreate for this user)
            TherapistAvailability::where('therapist_id', $user->id)->delete();
            foreach ($weekdaySlots as $slot) {
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

            $this->command->info("Seeded therapist: {$data['first_name']} {$data['last_name']}");
        }
    }
}
