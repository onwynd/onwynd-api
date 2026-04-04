<?php

namespace App\Services\Mail\Providers;

use App\Services\Mail\MailProviderException;
use App\Services\Mail\MailProviderInterface;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

abstract class BaseImapProvider implements MailProviderInterface
{
    protected ?Client $client = null;

    protected string $providerName = 'imap';

    abstract protected function getConfig(): array;

    protected function getClient(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        $config = $this->getConfig();
        $cm = new ClientManager;

        try {
            $this->client = $cm->make([
                'host' => $config['host'],
                'port' => $config['port'],
                'encryption' => $config['encryption'] ?? 'ssl',
                'validate_cert' => $config['validate_cert'] ?? true,
                'username' => $config['username'],
                'password' => $config['password'],
                'protocol' => 'imap',
            ]);

            $this->client->connect();

            return $this->client;
        } catch (\Exception $e) {
            Log::error("{$this->providerName} IMAP connection failed: ".$e->getMessage());
            throw new MailProviderException(
                "{$this->providerName} IMAP connection failed. ".$this->getErrorMessage(),
                $this->providerName
            );
        }
    }

    protected function getErrorMessage(): string
    {
        return 'Check your credentials and ensure IMAP is enabled.';
    }

    public function listMessages(string $folder = 'INBOX', int $page = 1, int $perPage = 20): array
    {
        $client = $this->getClient();
        $folderInstance = $client->getFolder($folder);

        $query = $folderInstance->query();
        $total = $query->count();
        $unread = $folderInstance->query()->unseen()->count();

        $messages = $query->limit($perPage, ($page - 1) * $perPage)->get();

        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = $this->formatMessageOverview($message);
        }

        return [
            'messages' => $formattedMessages,
            'total' => $total,
            'unread' => $unread,
        ];
    }

    public function getMessage(string $messageId): array
    {
        $client = $this->getClient();
        // IMAP message IDs can be tricky with Webklex, usually we use UID
        $message = $client->getMessageByUid($messageId);

        if (! $message) {
            throw new MailProviderException('Message not found', $this->providerName);
        }

        return $this->formatFullMessage($message);
    }

    public function sendMessage(array $data): array
    {
        // IMAP itself doesn't send mail, it's for reading.
        // We should use SMTP for sending. But for the interface, we'll implement it.
        // Usually, we'd use Laravel's Mail facade or a separate SMTP client.
        // For now, let's throw an exception or implement a basic SMTP send if needed.
        throw new \Exception('Send message not implemented for base IMAP yet. Use SMTP.');
    }

    public function markRead(string $messageId, bool $read = true): bool
    {
        $client = $this->getClient();
        $message = $client->getMessageByUid($messageId);
        if ($message) {
            return $read ? $message->setFlag('Seen') : $message->unsetFlag('Seen');
        }

        return false;
    }

    public function trashMessage(string $messageId): bool
    {
        $client = $this->getClient();
        $message = $client->getMessageByUid($messageId);
        if ($message) {
            return $message->delete();
        }

        return false;
    }

    public function getUnreadCount(): int
    {
        $client = $this->getClient();

        return $client->getFolder('INBOX')->query()->unseen()->count();
    }

    public function listFolders(): array
    {
        $client = $this->getClient();
        $folders = $client->getFolders();

        $result = [];
        foreach ($folders as $folder) {
            $result[] = [
                'name' => $folder->path,
                'display_name' => $folder->name,
                'unread' => $folder->query()->unseen()->count(),
            ];
        }

        return $result;
    }

    public function testConnection(): array
    {
        try {
            $this->getClient();

            return ['success' => true, 'message' => "Connected to {$this->providerName}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function formatMessageOverview(Message $message): array
    {
        return [
            'id' => $message->getUid(),
            'from' => $message->getFrom()[0]->full ?? 'Unknown',
            'subject' => $message->getSubject(),
            'date' => $message->getDate()->toIso8601String(),
            'is_read' => $message->getFlags()->has('seen'),
            'snippet' => substr(strip_tags($message->getTextBody()), 0, 100),
        ];
    }

    protected function formatFullMessage(Message $message): array
    {
        return [
            'id' => $message->getUid(),
            'from' => $message->getFrom()[0]->full ?? 'Unknown',
            'to' => $message->getTo()[0]->full ?? 'Unknown',
            'cc' => $message->getCc() ? $message->getCc()->map(fn ($c) => $c->full)->toArray() : [],
            'subject' => $message->getSubject(),
            'body_html' => $message->getHTMLBody(),
            'body_text' => $message->getTextBody(),
            'date' => $message->getDate()->toIso8601String(),
            'is_read' => $message->getFlags()->has('seen'),
            'attachments' => $this->formatAttachments($message),
        ];
    }

    protected function formatAttachments(Message $message): array
    {
        $attachments = [];
        foreach ($message->getAttachments() as $attachment) {
            $attachments[] = [
                'id' => $attachment->id,
                'name' => $attachment->name,
                'size' => $attachment->size,
                'mime_type' => $attachment->content_type,
            ];
        }

        return $attachments;
    }
}
