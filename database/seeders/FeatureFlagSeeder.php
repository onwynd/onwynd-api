<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class FeatureFlagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            [
                'key' => 'feature_eprescriptions_enabled',
                'value' => 'true',
                'group' => 'feature_flags',
                'type' => 'boolean',
            ],
            [
                'key' => 'feature_medication_tracking_enabled',
                'value' => 'true',
                'group' => 'feature_flags',
                'type' => 'boolean',
            ],
            [
                'key' => 'feature_secure_documents_enabled',
                'value' => 'true',
                'group' => 'feature_flags',
                'type' => 'boolean',
            ],
            [
                'key' => 'feature_gamification_enabled',
                'value' => 'true',
                'group' => 'feature_flags',
                'type' => 'boolean',
            ],
        ];

        foreach ($features as $feature) {
            Setting::updateOrCreate(
                ['key' => $feature['key']],
                $feature
            );
        }
    }
}
