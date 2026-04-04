<?php

namespace App\Services\Mail\Providers;

use App\Models\Setting;

class ZohoImapProvider extends BaseImapProvider
{
    protected string $providerName = 'Zoho';

    protected function getConfig(): array
    {
        return [
            'host' => Setting::getValue('mail_imap_host', 'imap.zoho.com'),
            'port' => Setting::getValue('mail_imap_port', 993),
            'username' => Setting::getValue('mail_imap_username', ''),
            'password' => Setting::getValue('mail_imap_password', ''), // Should be decrypted by Setting model if implemented
            'encryption' => 'ssl',
        ];
    }

    protected function getErrorMessage(): string
    {
        return 'Ensure IMAP is enabled on your Zoho plan (requires Mail Lite or higher) and the app password is correct.';
    }

    public function sendMessage(array $data): array
    {
        // Zoho specifically might need their API or SMTP.
        // For this abstraction, we'll assume SMTP is configured via Laravel's mailer
        // but the interface requires a provider-specific implementation.
        // For now, we'll use a placeholder or generic SMTP.
        throw new \Exception('Zoho sendMessage requires SMTP configuration.');
    }
}
