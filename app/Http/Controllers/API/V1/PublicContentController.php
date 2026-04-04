<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Models\Setting;

class PublicContentController extends BaseController
{
    public function therapistTerms()
    {
        $val = Setting::where('group', 'documents')->where('key', 'therapist_terms_md')->value('value');
        if (! $val) {
            $val = "# Therapist Pricing, Commission & Earnings\n\nThis page reflects live commission settings configured by Admin. Please refer to the on-page tables for current figures.";
        }

        return $this->sendResponse([
            'therapist_terms_md' => $val,
        ], 'Therapist terms content.');
    }

    public function commission()
    {
        $map = Setting::where('group', 'commission')->pluck('value', 'key')->toArray();
        $tiers = isset($map['tiers']) ? json_decode($map['tiers'], true) : null;
        if (! is_array($tiers)) {
            $tiers = [
                ['min' => 1, 'max' => 5000, 'therapist_keep_percent' => 90],
                ['min' => 5001, 'max' => 10000, 'therapist_keep_percent' => 85],
                ['min' => 10001, 'max' => 20000, 'therapist_keep_percent' => 82],
                ['min' => 20001, 'max' => null, 'therapist_keep_percent' => 80],
            ];
        }

        return $this->sendResponse([
            'tiers' => $tiers,
            'founding_enabled' => (bool) ($map['founding_enabled'] ?? true),
            'founding_discount_percent' => (float) ($map['founding_discount_percent'] ?? 3),
            'founding_duration_months' => (int) ($map['founding_duration_months'] ?? 24),
        ], 'Public commission settings.');
    }
}
