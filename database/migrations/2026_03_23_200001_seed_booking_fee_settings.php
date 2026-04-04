<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['group' => 'platform', 'key' => 'booking_fee_enabled'],
            ['value' => 'true', 'type' => 'boolean', 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('settings')->updateOrInsert(
            ['group' => 'platform', 'key' => 'booking_fee_ngn'],
            ['value' => '100', 'type' => 'string', 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('settings')->updateOrInsert(
            ['group' => 'platform', 'key' => 'booking_fee_usd'],
            ['value' => '0.10', 'type' => 'string', 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('settings')->updateOrInsert(
            ['group' => 'platform', 'key' => 'freemium_free_consults_per_month'],
            ['value' => '1', 'type' => 'string', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'platform')
            ->whereIn('key', [
                'booking_fee_enabled',
                'booking_fee_ngn',
                'booking_fee_usd',
                'freemium_free_consults_per_month',
            ])
            ->delete();
    }
};
