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
            ['group' => 'mail', 'key' => 'mail_provider', 'value' => 'zoho_imap', 'type' => 'string'],
            ['group' => 'mail', 'key' => 'mail_imap_host', 'value' => 'imap.zoho.com', 'type' => 'string'],
            ['group' => 'mail', 'key' => 'mail_imap_port', 'value' => '993', 'type' => 'integer'],
            ['group' => 'mail', 'key' => 'mail_imap_username', 'value' => '', 'type' => 'string'],
            ['group' => 'mail', 'key' => 'mail_imap_password', 'value' => '', 'type' => 'string'],
            ['group' => 'mail', 'key' => 'mail_google_account', 'value' => '', 'type' => 'string'],
            ['group' => 'mail', 'key' => 'mail_google_client_id', 'value' => '', 'type' => 'string'],
            ['group' => 'mail', 'key' => 'mail_google_client_secret', 'value' => '', 'type' => 'string'],
            ['group' => 'mail', 'key' => 'mail_google_refresh_token', 'value' => '', 'type' => 'string'],
            ['group' => 'mail', 'key' => 'mail_aapanel_host', 'value' => '127.0.0.1', 'type' => 'string'],
            ['group' => 'mail', 'key' => 'mail_aapanel_port', 'value' => '993', 'type' => 'integer'],
            ['group' => 'mail', 'key' => 'mail_aapanel_username', 'value' => '', 'type' => 'string'],
            ['group' => 'mail', 'key' => 'mail_aapanel_password', 'value' => '', 'type' => 'string'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['group' => $setting['group'], 'key' => $setting['key']],
                ['value' => $setting['value'], 'type' => $setting['type'], 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->where('group', 'mail')->delete();
    }
};
