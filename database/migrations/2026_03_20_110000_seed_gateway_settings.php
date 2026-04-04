<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Gateways enabled by default when going live */
    private const DEFAULTS_ENABLED = ['paystack', 'dodopayments'];

    public function up(): void
    {
        $gateways = ['paystack', 'flutterwave', 'klump', 'stripe', 'dodopayments'];

        foreach ($gateways as $gw) {
            $enabled = in_array($gw, self::DEFAULTS_ENABLED, true) ? 'true' : 'false';

            $rows = [
                ["{$gw}_enabled",          $enabled],
                ["{$gw}_mode",             'test'],
                ["{$gw}_test_public_key",  ''],
                ["{$gw}_test_secret_key",  ''],
                ["{$gw}_live_public_key",  ''],
                ["{$gw}_live_secret_key",  ''],
            ];

            foreach ($rows as [$key, $value]) {
                DB::table('settings')->updateOrInsert(
                    ['group' => 'gateways', 'key' => $key],
                    ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'gateways')->delete();
    }
};
