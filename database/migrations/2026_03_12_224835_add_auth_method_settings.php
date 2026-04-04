<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = [
            [
                'group' => 'auth',
                'key' => 'auth_google_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'auth',
                'key' => 'auth_phone_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'auth',
                'key' => 'auth_anonymous_enabled',
                'value' => 'true',
                'type' => 'boolean',
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
        DB::table('settings')->whereIn('key', [
            'auth_google_enabled',
            'auth_phone_enabled',
            'auth_anonymous_enabled',
        ])->delete();
    }
};
