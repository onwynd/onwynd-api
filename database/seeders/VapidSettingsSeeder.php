<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class VapidSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'vapid_public_key',
                'value' => 'BOuz8FzmHjQgj9ZFv9aazV-E2Q8Aq988sb0fZwfdMpwYrIb9-FrXHw9jUOlQFcNHysc1uXs5Kp4HZtufb2fPFyU',
                'group' => 'push_notifications',
                'type' => 'string',
            ],
            [
                'key' => 'vapid_private_key',
                'value' => 'WU8jEcLgNZUsdXyodFW1pQ-JSz-nRAmZtWU5nvgYvQA',
                'group' => 'push_notifications',
                'type' => 'string',
            ],
            [
                'key' => 'vapid_subject',
                'value' => 'mailto:admin@onwynd.com',
                'group' => 'push_notifications',
                'type' => 'string',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }
    }
}
