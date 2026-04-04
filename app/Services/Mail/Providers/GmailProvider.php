<?php

namespace App\Services\Mail\Providers;

use App\Models\Setting;
use App\Services\Mail\MailProviderException;
use App\Services\Mail\MailProviderInterface;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Gmail;
use Illuminate\Support\Facades\Cache;

class GmailProvider implements MailProviderInterface
{
    protected ?Gmail $service = null;

    protected string $providerName = 'Gmail';

    protected function getService(): Gmail
    {
        if ($this->service) {
            return $this->service;
        }

        $client = new Client;

        $serviceAccountPath = Setting::getValue('mail_google_service_account_path');
        $clientId = Setting::getValue('mail_google_client_id');

        if ($serviceAccountPath && file_exists(storage_path('app/'.$serviceAccountPath))) {
            $client->setAuthConfig(storage_path('app/'.$serviceAccountPath));
            $client->setSubject(Setting::getValue('mail_google_impersonate', 'hello@onwynd.com'));
        } elseif ($clientId) {
            $client->setClientId($clientId);
            $client->setClientSecret(Setting::getValue('mail_google_client_secret'));
            $client->refreshToken(Setting::getValue('mail_google_refresh_token'));
        } else {
            throw new MailProviderException('Gmail credentials missing.', $this->providerName);
        }

        $client->addScope(Gmail::GMAIL_MODIFY);
        $client->addScope(Gmail::GMAIL_SEND);

        $this->service = new Gmail($client);

        return $this->service;
    }

    public function listMessages(string $folder = 'INBOX', int $page = 1, int $perPage = 20): array
    {
        $service = $this->getService();
        $labelMap = [
            'INBOX' => 'INBOX',
            'Sent' => 'SENT',
            'Drafts' => 'DRAFT',
            'Trash' => 'TRASH',
            'Spam' => 'SPAM',
        ];

        $labelId = $labelMap[$folder] ?? $folder;

        $cacheKey = "gmail_list_{$labelId}_{$page}";

        return Cache::remember($cacheKey, 120, function () use ($service, $labelId, $perPage) {
            $results = $service->users_messages->listUsersMessages('me', [
                'labelIds' => [$labelId],
                'maxResults' => $perPage,
            ]);

            $messages = [];
            foreach ($results->getMessages() as $msg) {
                $messages[] = $this->formatMessageOverview($service->users_messages->get('me', $msg->getId(), ['format' => 'metadata']));
            }

            return [
                'messages' => $messages,
                'total' => $results->getResultSizeEstimate(),
                'unread' => $this->getUnreadCountForLabel($labelId),
            ];
        });
    }

    public function getMessage(string $messageId): array
    {
        $service = $this->getService();
        $msg = $service->users_messages->get('me', $messageId);

        return $this->formatFullMessage($msg);
    }

    public function sendMessage(array $data): array
    {
        $service = $this->getService();

        $message = new \Google\Service\Gmail\Message;
        $rawMessageString = "To: {$data['to']}\r\n";
        $rawMessageString .= "Subject: {$data['subject']}\r\n\r\n";
        $rawMessageString .= $data['body'];

        $rawMessage = strtr(base64_encode($rawMessageString), ['+' => '-', '/' => '_']);
        $message->setRaw($rawMessage);

        if (isset($data['reply_to_id'])) {
            $message->setThreadId($data['reply_to_id']);
        }

        $sentMsg = $service->users_messages->send('me', $message);

        return [
            'message_id' => $sentMsg->getId(),
            'sent_at' => now()->toIso8601String(),
        ];
    }

    public function markRead(string $messageId, bool $read = true): bool
    {
        $service = $this->getService();
        $mods = new \Google\Service\Gmail\ModifyMessageRequest;
        if ($read) {
            $mods->setRemoveLabelIds(['UNREAD']);
        } else {
            $mods->setAddLabelIds(['UNREAD']);
        }
        $service->users_messages->modify('me', $messageId, $mods);

        return true;
    }

    public function trashMessage(string $messageId): bool
    {
        $service = $this->getService();
        $service->users_messages->trash('me', $messageId);

        return true;
    }

    public function getUnreadCount(): int
    {
        return $this->getUnreadCountForLabel('INBOX');
    }

    protected function getUnreadCountForLabel(string $labelId): int
    {
        $service = $this->getService();
        $label = $service->users_labels->get('me', $labelId);

        return $label->getMessagesUnread() ?? 0;
    }

    public function listFolders(): array
    {
        $service = $this->getService();
        $results = $service->users_labels->listUsersLabels('me');

        $folders = [];
        foreach ($results->getLabels() as $label) {
            if ($label->getType() === 'system' || in_array($label->getName(), ['INBOX', 'SENT', 'DRAFT', 'TRASH', 'SPAM'])) {
                $folders[] = [
                    'name' => $label->getId(),
                    'display_name' => $label->getName(),
                    'unread' => $label->getMessagesUnread() ?? 0,
                ];
            }
        }

        return $folders;
    }

    public function testConnection(): array
    {
        try {
            $this->getService();

            return ['success' => true, 'message' => 'Connected to Gmail API'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function formatMessageOverview($msg): array
    {
        $payload = $msg->getPayload();
        $headers = $payload->getHeaders();

        $from = '';
        $subject = '';
        $date = '';

        foreach ($headers as $header) {
            if ($header->getName() === 'From') {
                $from = $header->getValue();
            }
            if ($header->getName() === 'Subject') {
                $subject = $header->getValue();
            }
            if ($header->getName() === 'Date') {
                $date = $header->getValue();
            }
        }

        return [
            'id' => $msg->getId(),
            'from' => $from,
            'subject' => $subject,
            'date' => Carbon::parse($date)->toIso8601String(),
            'is_read' => ! in_array('UNREAD', $msg->getLabelIds()),
            'snippet' => $msg->getSnippet(),
        ];
    }

    protected function formatFullMessage($msg): array
    {
        $payload = $msg->getPayload();
        $headers = $payload->getHeaders();

        $from = $to = $subject = $date = '';
        $cc = [];

        foreach ($headers as $header) {
            if ($header->getName() === 'From') {
                $from = $header->getValue();
            }
            if ($header->getName() === 'To') {
                $to = $header->getValue();
            }
            if ($header->getName() === 'Subject') {
                $subject = $header->getValue();
            }
            if ($header->getName() === 'Date') {
                $date = $header->getValue();
            }
            if ($header->getName() === 'Cc') {
                $cc = explode(',', $header->getValue());
            }
        }

        return [
            'id' => $msg->getId(),
            'from' => $from,
            'to' => $to,
            'cc' => $cc,
            'subject' => $subject,
            'body_html' => $this->getBody($payload, 'text/html'),
            'body_text' => $this->getBody($payload, 'text/plain'),
            'date' => Carbon::parse($date)->toIso8601String(),
            'is_read' => ! in_array('UNREAD', $msg->getLabelIds()),
            'attachments' => $this->getAttachments($payload),
        ];
    }

    protected function getBody($payload, $mimeType)
    {
        if ($payload->getMimeType() === $mimeType) {
            return base64_decode(strtr($payload->getBody()->getData(), ['-' => '+', '_' => '/']));
        }

        $parts = $payload->getParts();
        if ($parts) {
            foreach ($parts as $part) {
                $body = $this->getBody($part, $mimeType);
                if ($body) {
                    return $body;
                }
            }
        }

        return '';
    }

    protected function getAttachments($payload)
    {
        $attachments = [];
        $parts = $payload->getParts();
        if ($parts) {
            foreach ($parts as $part) {
                if ($part->getFilename()) {
                    $attachments[] = [
                        'id' => $part->getBody()->getAttachmentId(),
                        'name' => $part->getFilename(),
                        'size' => $part->getBody()->getSize(),
                        'mime_type' => $part->getMimeType(),
                    ];
                }
                $attachments = array_merge($attachments, $this->getAttachments($part));
            }
        }

        return $attachments;
    }
}
