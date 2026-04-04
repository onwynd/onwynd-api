<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            // Web app (onwynd.com) protections
            ['key' => 'ip_protection_web_enabled',        'value' => 'false', 'group' => 'ip_protection', 'type' => 'boolean'],
            ['key' => 'ip_protect_web_devtools',          'value' => 'false', 'group' => 'ip_protection', 'type' => 'boolean'],
            ['key' => 'ip_protect_web_rightclick',        'value' => 'false', 'group' => 'ip_protection', 'type' => 'boolean'],
            ['key' => 'ip_protect_web_textselection',     'value' => 'false', 'group' => 'ip_protection', 'type' => 'boolean'],
            ['key' => 'ip_protect_web_keyboard',          'value' => 'false', 'group' => 'ip_protection', 'type' => 'boolean'],
            ['key' => 'ip_protect_web_dragging',          'value' => 'false', 'group' => 'ip_protection', 'type' => 'boolean'],
            ['key' => 'ip_protect_web_log_attempts',      'value' => 'false', 'group' => 'ip_protection', 'type' => 'boolean'],

            // Dashboard (dashboard.onwynd.com) protections
            ['key' => 'ip_protection_dashboard_enabled',  'value' => 'false', 'group' => 'ip_protection', 'type' => 'boolean'],
            ['key' => 'ip_protect_dash_devtools',         'value' => 'false', 'group' => 'ip_protection', 'type' => 'boolean'],
            ['key' => 'ip_protect_dash_rightclick',       'value' => 'false', 'group' => 'ip_protection', 'type' => 'boolean'],
            ['key' => 'ip_protect_dash_textselection',    'value' => 'false', 'group' => 'ip_protection', 'type' => 'boolean'],
            ['key' => 'ip_protect_dash_keyboard',         'value' => 'false', 'group' => 'ip_protection', 'type' => 'boolean'],
            ['key' => 'ip_protect_dash_clipboard',        'value' => 'false', 'group' => 'ip_protection', 'type' => 'boolean'],
            ['key' => 'ip_protect_dash_log_attempts',     'value' => 'false', 'group' => 'ip_protection', 'type' => 'boolean'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    public function down(): void
    {
        $keys = [
            'ip_protection_web_enabled', 'ip_protect_web_devtools',
            'ip_protect_web_rightclick', 'ip_protect_web_textselection',
            'ip_protect_web_keyboard', 'ip_protect_web_dragging',
            'ip_protect_web_log_attempts', 'ip_protection_dashboard_enabled',
            'ip_protect_dash_devtools', 'ip_protect_dash_rightclick',
            'ip_protect_dash_textselection', 'ip_protect_dash_keyboard',
            'ip_protect_dash_clipboard', 'ip_protect_dash_log_attempts',
        ];
        Setting::whereIn('key', $keys)->delete();
    }
};
