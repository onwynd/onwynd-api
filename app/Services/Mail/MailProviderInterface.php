<?php

namespace App\Services\Mail;

interface MailProviderInterface
{
    /**
     * List messages in a folder/label
     *
     * @param  string  $folder  e.g. 'INBOX', 'Sent', 'Drafts', 'Trash', 'Spam'
     * @param  int  $page  1-indexed page number
     * @param  int  $perPage  messages per page (default 20)
     * @return array { messages: [], total: int, unread: int }
     */
    public function listMessages(
        string $folder = 'INBOX',
        int $page = 1,
        int $perPage = 20
    ): array;

    /**
     * Get a single message with full body
     *
     * @param  string  $messageId  provider-specific message ID
     * @return array {
     *               id, from, to, cc, subject, body_html, body_text,
     *               date, is_read, attachments[]
     *               }
     */
    public function getMessage(string $messageId): array;

    /**
     * Send a new email or reply to an existing thread
     *
     * @param  array  $data  {
     *                       to: string, subject: string, body: string,
     *                       reply_to_id?: string (message ID being replied to)
     *                       }
     * @return array { message_id: string, sent_at: datetime }
     */
    public function sendMessage(array $data): array;

    /**
     * Mark a message as read or unread
     */
    public function markRead(string $messageId, bool $read = true): bool;

    /**
     * Move a message to trash
     */
    public function trashMessage(string $messageId): bool;

    /**
     * Get unread count for inbox
     */
    public function getUnreadCount(): int;

    /**
     * List available folders/labels
     *
     * @return array [{ name: string, display_name: string, unread: int }]
     */
    public function listFolders(): array;

    /**
     * Test the connection with current credentials
     *
     * @return array { success: bool, message: string }
     */
    public function testConnection(): array;
}
