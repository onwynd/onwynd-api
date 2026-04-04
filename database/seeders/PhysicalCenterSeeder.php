<?php

namespace Database\Seeders;

use App\Models\CenterService;
use App\Models\PhysicalCenter;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PhysicalCenterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure a manager exists
        $manager = User::first() ?? User::factory()->create();

        $centers = [
            [
                'name' => 'Onwynd Lagos Hub',
                'address_line1' => '123 Victoria Island',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'phone' => '+234 800 123 4567',
                'email' => 'lagos@onwynd.com',
            ],
            [
                'name' => 'Onwynd Abuja Center',
                'address_line1' => '456 Maitama District',
                'city' => 'Abuja',
                'state' => 'FCT',
                'phone' => '+234 800 234 5678',
                'email' => 'abuja@onwynd.com',
            ],
            [
                'name' => 'Onwynd Port Harcourt Wellness',
                'address_line1' => '789 GRA Phase 2',
                'city' => 'Port Harcourt',
                'state' => 'Rivers',
                'phone' => '+234 800 345 6789',
                'email' => 'ph@onwynd.com',
            ],
        ];

        $serviceTypes = ['VR_therapy', 'massage', 'yoga', 'counseling', 'wellness_coaching', 'group_class'];

        foreach ($centers as $data) {
            $center = PhysicalCenter::firstOrCreate(
                ['email' => $data['email']],
                array_merge($data, [
                    'uuid' => (string) Str::uuid(),
                    'manager_id' => $manager->id,
                    'capacity' => 50,
                    'operating_hours' => [
                        'monday' => '09:00-17:00',
                        'friday' => '09:00-17:00',
                    ],
                    'services_offered' => $serviceTypes, // Simplified array
                    'is_active' => true,
                ])
            );

            // Create specific service entries for filtering
            foreach ($serviceTypes as $type) {
                CenterService::firstOrCreate(
                    [
                        'center_id' => $center->id,
                        'service_type' => $type,
                    ],
                    [
                        'service_name' => ucwords(str_replace('_', ' ', $type)),
                        'description' => "Professional {$type} session",
                        'duration_minutes' => 60,
                        'price' => 5000.00,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
