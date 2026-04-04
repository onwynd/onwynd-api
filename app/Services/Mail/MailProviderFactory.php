<?php

namespace App\Services\Mail;

use App\Models\Setting;
use App\Services\Mail\Providers\AaPanelMailProvider;
use App\Services\Mail\Providers\GmailProvider;
use App\Services\Mail\Providers\ZohoImapProvider;

class MailProviderFactory
{
    public static function make(): MailProviderInterface
    {
        // Read active provider from platform_settings table
        // Key: 'mail_provider'
        // Values: 'zoho_imap' | 'gmail' | 'aapanel'
        // Default: 'zoho_imap'

        $provider = Setting::getValue('mail_provider', 'zoho_imap');

        return match ($provider) {
            'gmail' => new GmailProvider,
            'aapanel' => new AaPanelMailProvider,
            default => new ZohoImapProvider,
        };
    }
}
