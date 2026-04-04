<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Models\NotificationSetting;
use Illuminate\Http\Request;

/**
 * Manages per-user notification preferences via the notification_settings table.
 *
 * GET  /api/v1/notifications/preferences  — returns current prefs (creates row with defaults on first access)
 * PUT  /api/v1/notifications/preferences  — saves changes
 *
 * Base categories (all authenticated users):
 *   session_reminders, wellbeing_checkins, new_messages,
 *   payment_receipts, platform_updates, promotional
 *
 * Role-gated categories — only returned/writeable when the user holds the right role:
 *   distress_alerts   → clinical_advisor, therapist, admin, super_admin, coo, ceo
 *   member_distress   → institution_admin, university_admin, hr
 *   org_credits       → institution_admin, university_admin, hr, coo, ceo
 *
 * severity_threshold is stored inside channel_preferences JSON, NOT as a flat column.
 * Valid values: 'any' | 'medium' | 'high' | 'critical'
 *
 * Data flow (update):
 *
 *   INCOMING JSON
 *       │
 *       ▼
 *   validateRequest()            ← strips non-boolean channel values,
 *       │                           allows severity_threshold string
 *       ▼
 *   authorizedCategories()       ← drops categories the user's role cannot write
 *       │
 *       ▼
 *   mapToDbColumns()             ← base categories → flat boolean columns
 *       │
 *       ▼
 *   mergeChannelPrefs()          ← role-gated + granular overrides → JSON column
 *       │
 *       ▼
 *   $settings->update()
 *       │
 *       ▼
 *   toFrontendShape()            ← flat columns + JSON → nested response (role-filtered)
 */
class NotificationPreferencesController extends BaseController
{
    /**
     * Role → extra notification categories they may read/write.
     * Mirrors ROLE_EXTRA_CATEGORIES in onwynd-dashboard/lib/api/user.ts.
     */
    private const ROLE_EXTRA_CATEGORIES = [
        'clinical_advisor'  => ['distress_alerts'],
        'therapist'         => ['distress_alerts'],
        'admin'             => ['distress_alerts'],
        'super_admin'       => ['distress_alerts'],
        'coo'               => ['distress_alerts', 'org_credits'],
        'ceo'               => ['distress_alerts', 'org_credits'],
        'institution_admin' => ['member_distress', 'org_credits'],
        'university_admin'  => ['member_distress', 'org_credits'],
        'hr'                => ['member_distress', 'org_credits'],
    ];

    /** Categories every user can access regardless of role. */
    private const BASE_CATEGORIES = [
        'session_reminders',
        'wellbeing_checkins',
        'new_messages',
        'payment_receipts',
        'platform_updates',
        'promotional',
    ];

    /** Severity threshold: the only non-boolean leaf value permitted. */
    private const SEVERITY_VALUES = ['any', 'medium', 'high', 'critical'];

    // ─────────────────────────────────────────────────────────────────────────

    public function show(Request $request)
    {
        $settings = $this->getOrCreate($request->user());
        return $this->sendResponse(
            $this->toFrontendShape($settings, $request->user()),
            'Notification preferences retrieved.'
        );
    }

    public function update(Request $request)
    {
        // Custom validation: channel values are boolean OR severity_threshold string.
        $request->validate([
            'preferences'   => 'required|array',
            'preferences.*' => 'array',
        ]);

        // Validate individual channel values manually so severity_threshold is allowed.
        $this->validateChannelValues($request->input('preferences', []));

        $user     = $request->user();
        $settings = $this->getOrCreate($user);

        // Drop any categories the user's role cannot write — security guard.
        $incoming = $this->filterToAuthorizedCategories(
            $request->input('preferences'),
            $user
        );

        // Map base categories → flat boolean DB columns.
        $updates = $this->mapToDbColumns($incoming);

        // Merge all incoming categories (including role-gated ones) into channel_preferences JSON.
        $channelPrefs = $settings->channel_preferences ?? [];
        foreach ($incoming as $category => $channels) {
            // Normalise: cast boolean channels; leave severity_threshold as-is.
            $normalised = [];
            foreach ($channels as $key => $value) {
                if ($key === 'severity_threshold') {
                    $normalised[$key] = in_array($value, self::SEVERITY_VALUES, true) ? $value : 'any';
                } else {
                    $normalised[$key] = (bool) $value;
                }
            }
            $channelPrefs[$category] = $normalised;
        }
        $updates['channel_preferences'] = $channelPrefs;

        $settings->update($updates);

        return $this->sendResponse(
            $this->toFrontendShape($settings->fresh(), $user),
            'Notification preferences updated.'
        );
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function getOrCreate($user): NotificationSetting
    {
        return NotificationSetting::firstOrCreate(
            ['user_id' => $user->id],
            NotificationSetting::getDefaults()
        );
    }

    /**
     * Returns the set of extra category keys the user's roles grant access to.
     */
    private function extraCategoriesForUser($user): array
    {
        $allRoles = method_exists($user, 'allRoles') ? $user->allRoles() : [];
        $extra    = [];
        foreach ($allRoles as $role) {
            foreach (self::ROLE_EXTRA_CATEGORIES[$role] ?? [] as $cat) {
                $extra[$cat] = true;
            }
        }
        return array_keys($extra);
    }

    /**
     * Strip categories from $incoming that the user is not authorised to touch.
     */
    private function filterToAuthorizedCategories(array $incoming, $user): array
    {
        $allowed = array_merge(self::BASE_CATEGORIES, $this->extraCategoriesForUser($user));
        return array_intersect_key($incoming, array_flip($allowed));
    }

    /**
     * Map base-category preferences onto flat boolean DB columns.
     */
    private function mapToDbColumns(array $incoming): array
    {
        $updates = [];

        if (isset($incoming['session_reminders'])) {
            $sr = $incoming['session_reminders'];
            if (array_key_exists('email', $sr))    $updates['email_notifications']    = (bool) $sr['email'];
            if (array_key_exists('push', $sr))     $updates['push_notifications']     = (bool) $sr['push'];
            if (array_key_exists('whatsapp', $sr)) $updates['whatsapp_notifications'] = (bool) $sr['whatsapp'];
            // in_app for session_reminders is mandatory (database channel) — keep flat columns in sync.
            $updates['session_reminders']     = true;
            $updates['appointment_reminders'] = true;
        }

        if (isset($incoming['wellbeing_checkins'])) {
            $wc = $incoming['wellbeing_checkins'];
            // Flat column is true when either sub-channel is on.
            $updates['wellbeing_checkins'] = (bool) (($wc['push'] ?? false) || ($wc['in_app'] ?? false));
        }

        if (isset($incoming['new_messages'])) {
            $nm = $incoming['new_messages'];
            $updates['message_notifications'] = (bool) (($nm['push'] ?? false) || ($nm['in_app'] ?? false));
        }

        if (isset($incoming['payment_receipts'])) {
            $pr = $incoming['payment_receipts'];
            $updates['billing_notifications'] = (bool) (($pr['email'] ?? false) || ($pr['in_app'] ?? false));
        }

        if (isset($incoming['platform_updates'])) {
            $pu = $incoming['platform_updates'];
            $updates['platform_updates'] = (bool) (($pu['email'] ?? false) || ($pu['in_app'] ?? false));
        }

        if (isset($incoming['promotional'])) {
            $updates['promotional_emails'] = (bool) ($incoming['promotional']['email'] ?? false);
        }

        // Role-gated categories have no flat DB column — they live entirely in channel_preferences.
        // (distress_alerts, member_distress, org_credits)

        return $updates;
    }

    /**
     * Throw a 422 if any channel value is neither boolean nor a valid severity string.
     */
    private function validateChannelValues(array $preferences): void
    {
        foreach ($preferences as $category => $channels) {
            if (! is_array($channels)) continue;
            foreach ($channels as $key => $value) {
                if ($key === 'severity_threshold') {
                    if (! in_array($value, self::SEVERITY_VALUES, true)) {
                        abort(422, "Invalid severity_threshold '{$value}' for category '{$category}'.");
                    }
                } else {
                    if (! is_bool($value)) {
                        abort(422, "Channel value for '{$category}.{$key}' must be boolean.");
                    }
                }
            }
        }
    }

    /**
     * Convert flat DB columns + channel_preferences JSON → nested frontend shape.
     * Only includes role-gated categories when the user holds the right role.
     */
    private function toFrontendShape(NotificationSetting $s, $user): array
    {
        $cp    = $s->channel_preferences ?? [];
        $extra = $this->extraCategoriesForUser($user);

        $shape = [
            'session_reminders' => array_merge([
                'email'    => $s->email_notifications    ?? true,
                'push'     => $s->push_notifications     ?? true,
                'whatsapp' => $s->whatsapp_notifications ?? true,
                'in_app'   => true, // always on — mandatory database channel
            ], $cp['session_reminders'] ?? []),

            'wellbeing_checkins' => array_merge([
                'push'   => $s->wellbeing_checkins ?? true,
                'in_app' => $s->wellbeing_checkins ?? true,
            ], $cp['wellbeing_checkins'] ?? []),

            'new_messages' => array_merge([
                'push'   => $s->message_notifications ?? true,
                'in_app' => $s->message_notifications ?? true,
            ], $cp['new_messages'] ?? []),

            'payment_receipts' => array_merge([
                'email'  => $s->billing_notifications ?? true,
                'in_app' => $s->billing_notifications ?? true,
            ], $cp['payment_receipts'] ?? []),

            'platform_updates' => array_merge([
                'email'  => $s->platform_updates ?? true,
                'in_app' => $s->platform_updates ?? true,
            ], $cp['platform_updates'] ?? []),

            'promotional' => array_merge([
                'email' => $s->promotional_emails ?? false,
            ], $cp['promotional'] ?? []),
        ];

        // Append role-gated categories — only when the user's role grants access.
        if (in_array('distress_alerts', $extra, true)) {
            $shape['distress_alerts'] = array_merge([
                'email'              => true,
                'in_app'             => true,
                'severity_threshold' => 'any',
            ], $cp['distress_alerts'] ?? []);
        }

        if (in_array('member_distress', $extra, true)) {
            $shape['member_distress'] = array_merge([
                'email'              => true,
                'in_app'             => true,
                'severity_threshold' => 'any',
            ], $cp['member_distress'] ?? []);
        }

        if (in_array('org_credits', $extra, true)) {
            $shape['org_credits'] = array_merge([
                'email'  => true,
                'in_app' => true,
            ], $cp['org_credits'] ?? []);
        }

        return $shape;
    }
}
