<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            [
                'group' => 'platform',
                'key' => 'regional_matching_state',
                'value' => 'on',
                'type' => 'string',
            ],
            [
                'group' => 'platform',
                'key' => 'enforce_currency_routing',
                'value' => 'true',
                'type' => 'boolean',
            ],
            [
                'group' => 'platform',
                'key' => 'platform_launch_date',
                'value' => now()->toDateString(),
                'type' => 'string',
            ],
        ];

        foreach ($defaults as $setting) {
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
            ->whereIn('key', ['regional_matching_state', 'enforce_currency_routing', 'platform_launch_date'])
            ->delete();
    }
};
