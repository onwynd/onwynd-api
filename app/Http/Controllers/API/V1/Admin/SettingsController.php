<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends BaseController
{
    /**
     * Cast a raw DB value to its proper PHP type based on the 'type' column.
     */
    private function cast(string $type, ?string $value): mixed
    {
        return match ($type) {
            'boolean' => in_array(strtolower((string) $value), ['true', '1', 'yes'], true),
            'integer' => (int) $value,
            'float'   => (float) $value,
            'json'    => json_decode($value, true),
            default   => $value,
        };
    }

    /**
     * GET /api/v1/admin/settings
     * Returns all settings grouped by their 'group' column.
     */
    public function index(Request $request)
    {
        $rows = Setting::all();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->group][$row->key] = $this->cast($row->type, $row->value);
        }

        return $this->sendResponse($grouped, 'Settings retrieved successfully.');
    }

    /**
     * PUT /api/v1/admin/settings
     * Bulk update — expects { group => { key => value, … }, … }
     */
    public function update(Request $request)
    {
        $payload = $request->all();

        DB::transaction(function () use ($payload) {
            foreach ($payload as $group => $settings) {
                if (!is_array($settings)) {
                    continue;
                }
                foreach ($settings as $key => $value) {
                    $this->upsertSetting($group, $key, $value);
                }
            }
        });

        return $this->sendResponse([], 'Settings updated successfully.');
    }

    /**
     * PUT /api/v1/admin/settings/{group}
     * Update a single group of settings — expects flat { key => value } map.
     */
    public function updateGroup(Request $request, string $group)
    {
        $payload = $request->all();

        DB::transaction(function () use ($group, $payload) {
            foreach ($payload as $key => $value) {
                $this->upsertSetting($group, $key, $value);
            }
        });

        // Return the updated group
        $rows = Setting::where('group', $group)->get();
        $result = [];
        foreach ($rows as $row) {
            $result[$row->key] = $this->cast($row->type, $row->value);
        }

        return $this->sendResponse($result, ucfirst($group) . ' settings updated successfully.');
    }

    /**
     * POST /api/v1/admin/settings/vat/toggle
     */
    public function toggleVat(Request $request)
    {
        $current = Setting::where('key', 'vat_enabled')->first();
        $newValue = $current ? ($current->value === 'true' ? 'false' : 'true') : 'true';

        DB::table('settings')->updateOrInsert(
            ['key' => 'vat_enabled'],
            ['group' => 'platform', 'type' => 'boolean', 'value' => $newValue, 'updated_at' => now()]
        );

        return $this->sendResponse(['vat_enabled' => $newValue === 'true'], 'VAT toggled successfully.');
    }

    /**
     * PUT /api/v1/admin/settings/vat/rate
     * Expects { rate: 0.075 }
     */
    public function updateVatRate(Request $request)
    {
        $request->validate(['rate' => 'required|numeric|min:0|max:1']);

        DB::table('settings')->updateOrInsert(
            ['key' => 'vat_rate'],
            ['group' => 'platform', 'type' => 'string', 'value' => (string) $request->rate, 'updated_at' => now()]
        );

        return $this->sendResponse(['vat_rate' => $request->rate], 'VAT rate updated successfully.');
    }

    /**
     * PUT /api/v1/admin/settings/booking-fee
     * Expects { booking_fee_ngn, booking_fee_usd, booking_fee_enabled? }
     */
    public function updateBookingFee(Request $request)
    {
        $request->validate([
            'booking_fee_ngn'     => 'nullable|numeric|min:0',
            'booking_fee_usd'     => 'nullable|numeric|min:0',
            'booking_fee_enabled' => 'nullable|boolean',
        ]);

        $fields = array_filter([
            'booking_fee_ngn'     => $request->booking_fee_ngn !== null ? (string) $request->booking_fee_ngn : null,
            'booking_fee_usd'     => $request->booking_fee_usd !== null ? (string) $request->booking_fee_usd : null,
            'booking_fee_enabled' => $request->has('booking_fee_enabled') ? ($request->booking_fee_enabled ? 'true' : 'false') : null,
        ], fn($v) => $v !== null);

        foreach ($fields as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['group' => 'platform', 'type' => 'string', 'value' => $value, 'updated_at' => now()]
            );
        }

        return $this->sendResponse($fields, 'Booking fee updated successfully.');
    }

    /**
     * POST /api/v1/admin/settings/ambassador-referral/toggle
     */
    public function toggleAmbassadorReferralTracking(Request $request)
    {
        $current = Setting::where('key', 'ambassador_referral_tracking_enabled')->first();
        $newValue = $current ? ($current->value === 'true' ? 'false' : 'true') : 'true';

        DB::table('settings')->updateOrInsert(
            ['key' => 'ambassador_referral_tracking_enabled'],
            ['group' => 'platform', 'type' => 'boolean', 'value' => $newValue, 'updated_at' => now()]
        );

        return $this->sendResponse(
            ['ambassador_referral_tracking_enabled' => $newValue === 'true'],
            'Ambassador referral tracking toggled successfully.'
        );
    }

    /**
     * GET /api/v1/platform/branding  (public, unauthenticated)
     * GET /api/v1/admin/platform/branding
     */
    public function getPlatformBranding(Request $request)
    {
        $rows = Setting::where('group', 'branding')->get();

        $branding = [];
        foreach ($rows as $row) {
            $branding[$row->key] = $this->cast($row->type, $row->value);
        }

        // Defaults when nothing is seeded yet
        $branding = array_merge([
            'theme' => 'default',
            'font'  => 'inter',
        ], $branding);

        return $this->sendResponse($branding, 'Platform branding retrieved successfully.');
    }

    // -------------------------------------------------------------------------

    private function upsertSetting(string $group, string $key, mixed $value): void
    {
        $existing = Setting::where('key', $key)->first();
        // Preserve the existing type if known; otherwise infer from the incoming value
        $type = $existing?->type ?? $this->inferType($value);

        $serialized = match (true) {
            is_bool($value)  => $value ? 'true' : 'false',
            is_array($value) => json_encode($value),
            default          => (string) $value,
        };

        if ($existing) {
            // Update only value/type — keep the original group from the migration seed
            $existing->update(['type' => $type, 'value' => $serialized]);
        } else {
            Setting::create(['group' => $group, 'key' => $key, 'type' => $type, 'value' => $serialized]);
        }
    }

    private function inferType(mixed $value): string
    {
        if (is_bool($value)) return 'boolean';
        if (is_int($value))  return 'integer';
        if (is_float($value)) return 'float';
        if (is_array($value)) return 'json';
        return 'string';
    }
}
