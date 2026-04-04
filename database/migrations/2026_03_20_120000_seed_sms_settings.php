<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            // Master switch + credentials
            ['sms_enabled',                   'true'],
            ['termii_api_key',                ''],
            ['termii_sender_id',              'ONWYND'],
            ['otp_expiry_mins',               '10'],
            ['otp_length',                    '6'],
            // Per-event SMS toggles
            ['event_otp',                     'true'],
            ['event_session_reminder',        'true'],
            ['event_appointment',             'true'],
            ['event_group_reminder',          'false'],
            ['event_payment_confirmation',    'false'],
            // WhatsApp master switch, provider + credentials
            ['whatsapp_enabled',              'false'],
            ['whatsapp_provider',             'qr'],  // 'qr' | 'meta' | 'termii'
            ['whatsapp_phone_number_id',      ''],   // Termii sender number (provider=termii)
            // Meta / Facebook Cloud API credentials
            ['meta_wa_phone_number_id',       ''],   // Meta phone number ID from Business Manager
            ['meta_wa_access_token',          ''],   // Meta permanent system user token
            // Per-event WhatsApp toggles
            ['wa_event_session_reminder',     'false'],
            ['wa_event_appointment',          'false'],
            ['wa_event_group_reminder',       'false'],
            ['wa_event_payment_confirmation', 'false'],
            // Reminder schedule: how many minutes before event to send (comma-separated for sequences)
            ['reminder_schedule_session',     '1440,60'],  // 24h then 1h before
            ['reminder_schedule_group',       '60,15'],    // 1h then 15min before
        ];

        foreach ($rows as [$key, $value]) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'sms', 'key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'sms')->delete();
    }
};
