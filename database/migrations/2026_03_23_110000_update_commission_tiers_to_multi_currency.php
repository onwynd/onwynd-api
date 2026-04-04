<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tiersJson = json_encode([
            'NGN' => [
                ['min' => 1, 'max' => 5000, 'therapist_keep_percent' => 90, 'label' => '₦1 – ₦5,000'],
                ['min' => 5001, 'max' => 10000, 'therapist_keep_percent' => 85, 'label' => '₦5,001 – ₦10,000'],
                ['min' => 10001, 'max' => 20000, 'therapist_keep_percent' => 82, 'label' => '₦10,001 – ₦20,000'],
                ['min' => 20001, 'max' => null, 'therapist_keep_percent' => 80, 'label' => '₦20,001 and above'],
            ],
            'USD' => [
                ['min' => 1, 'max' => 35, 'therapist_keep_percent' => 90, 'label' => '$1 – $35'],
                ['min' => 36, 'max' => 70, 'therapist_keep_percent' => 85, 'label' => '$36 – $70'],
                ['min' => 71, 'max' => 140, 'therapist_keep_percent' => 82, 'label' => '$71 – $140'],
                ['min' => 141, 'max' => null, 'therapist_keep_percent' => 80, 'label' => '$141 and above'],
            ],
        ]);

        DB::table('settings')->updateOrInsert(
            ['group' => 'commission', 'key' => 'tiers'],
            ['value' => $tiersJson, 'type' => 'json', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        $legacyJson = json_encode([
            ['min' => 1, 'max' => 5000, 'therapist_keep_percent' => 90],
            ['min' => 5001, 'max' => 10000, 'therapist_keep_percent' => 85],
            ['min' => 10001, 'max' => 20000, 'therapist_keep_percent' => 82],
            ['min' => 20001, 'max' => null, 'therapist_keep_percent' => 80],
        ]);

        DB::table('settings')->updateOrInsert(
            ['group' => 'commission', 'key' => 'tiers'],
            ['value' => $legacyJson, 'type' => 'json', 'updated_at' => now(), 'created_at' => now()]
        );
    }
};
