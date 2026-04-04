<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            ['key' => 'auto_detect',              'value' => 'true',          'label' => 'Enable auto geo-detection'],
            ['key' => 'regional_testimonials',    'value' => 'true',          'label' => 'Show region-appropriate testimonials'],
            ['key' => 'regional_pricing',         'value' => 'true',          'label' => 'Show region-appropriate pricing (hide NGN from foreign users)'],
            ['key' => 'regional_phone',           'value' => 'true',          'label' => 'Show region-appropriate phone numbers'],
            ['key' => 'regional_payment_gateway', 'value' => 'true',          'label' => 'Route to region-appropriate payment gateway'],
            ['key' => 'international_gateway',    'value' => 'dodopayments',  'label' => 'Gateway for international (USD) users'],
            ['key' => 'stripe_paused',            'value' => 'true',          'label' => 'Pause Stripe (use DodoPayments for USD instead)'],
        ];

        foreach ($defaults as $setting) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'geo', 'key' => $setting['key']],
                ['value' => $setting['value'], 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'geo')->delete();
    }
};
