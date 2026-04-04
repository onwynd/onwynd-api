<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = [
            [
                'key' => 'platform_fee_ngn',
                'value' => '1500',
                'group' => 'payments',
                'type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'platform_fee_usd',
                'value' => '5',
                'group' => 'payments',
                'type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('settings')->insert($settings);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->whereIn('key', ['platform_fee_ngn', 'platform_fee_usd'])
            ->delete();
    }
};
