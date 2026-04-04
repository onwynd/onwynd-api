<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed default SMS message templates.
 *
 * Placeholder syntax: {variable_name}
 * Templates are rendered at send-time by NotificationService::renderTemplate().
 *
 * Termii note: the 'generic' channel accepts free-form text and requires no
 * pre-registration. If you switch to a DND-compliant channel you must also
 * register the exact approved text in the Termii dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            'otp' => "Your {app_name} verification code is {code}. Valid for {expiry_mins} minutes. Do not share this code with anyone.",

            'session_reminder' => "Hi {name}, this is a reminder that your therapy session with {therapist_name} is coming up at {session_time}. Log in via {app_name} to join.",

            'appointment' => "Hi {name}, your appointment with {therapist_name} has been confirmed for {date} at {time}. See you then — {app_name} Team.",

            'group_reminder' => "Hi {name}, your group therapy session \"{session_title}\" starts at {time}. Join via your {app_name} dashboard.",

            'payment_confirmation' => "Hi {name}, payment of {amount} {currency} for your {plan_name} subscription was successful. Thank you — {app_name}.",
        ];

        foreach ($templates as $key => $body) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'sms_templates', 'key' => $key],
                ['value' => $body, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'sms_templates')->delete();
    }
};
