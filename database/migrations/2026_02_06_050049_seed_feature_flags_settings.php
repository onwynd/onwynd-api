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
                'key' => 'feature_eprescriptions_enabled',
                'value' => 'true',
                'group' => 'features',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'feature_medication_tracking_enabled',
                'value' => 'true',
                'group' => 'features',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'feature_secure_documents_enabled',
                'value' => 'true',
                'group' => 'features',
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
        DB::table('settings')
            ->whereIn('key', [
                'feature_eprescriptions_enabled',
                'feature_medication_tracking_enabled',
                'feature_secure_documents_enabled',
            ])
            ->delete();
    }
};
