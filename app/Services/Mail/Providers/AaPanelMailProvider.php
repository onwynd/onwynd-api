<?php

namespace App\Services\Mail\Providers;

use App\Models\Setting;

class AaPanelMailProvider extends BaseImapProvider
{
    protected string $providerName = 'aaPanel';

    protected function getConfig(): array
    {
        return [
            'host' => Setting::getValue('mail_aapanel_host', '127.0.0.1'),
            'port' => Setting::getValue('mail_aapanel_port', 993),
            'username' => Setting::getValue('mail_aapanel_username', ''),
            'password' => Setting::getValue('mail_aapanel_password', ''),
            'encryption' => 'ssl',
        ];
    }

    protected function getErrorMessage(): string
    {
        return 'Ensure the mail server module is installed and running in aaPanel, and the mailbox exists with the correct password.';
    }

    public function sendMessage(array $data): array
    {
        throw new \Exception('aaPanel sendMessage requires SMTP configuration.');
    }
}
