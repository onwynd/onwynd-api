<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'group' => 'platform',
                'key'   => 'vat_enabled',
                'value' => 'false',
                'type'  => 'boolean',
            ],
            [
                'group' => 'platform',
                'key'   => 'vat_rate',
                'value' => '0.075',
                'type'  => 'string',
            ],
            [
                'group' => 'platform',
                'key'   => 'vat_label',
                'value' => 'VAT (7.5%)',
                'type'  => 'string',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['group' => $setting['group'], 'key' => $setting['key']],
                array_merge($setting, ['updated_at' => now(), 'created_at' => now()])
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'platform')
            ->whereIn('key', ['vat_enabled', 'vat_rate', 'vat_label'])
            ->delete();
    }
};
