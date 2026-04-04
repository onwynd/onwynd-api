<?php

namespace App\Services;

/**
 * Scans text for contact information (phone numbers, emails, social handles)
 * to prevent therapist/patient off-platform contact attempts via AI chat.
 */
class ContactDetectionService
{
    /**
     * Returns true if the message contains contact information.
     */
    public function hasContactInfo(string $message): bool
    {
        return ! empty($this->detect($message));
    }

    /**
     * Returns an array of detected contact types found in the message.
     * Possible values: 'phone', 'email', 'social_handle', 'whatsapp'
     */
    public function detect(string $message): array
    {
        $found = [];

        if ($this->containsPhone($message)) {
            $found[] = 'phone';
        }

        if ($this->containsEmail($message)) {
            $found[] = 'email';
        }

        if ($this->containsWhatsApp($message)) {
            $found[] = 'whatsapp';
        }

        if ($this->containsSocialHandle($message)) {
            $found[] = 'social_handle';
        }

        return array_unique($found);
    }

    private function containsPhone(string $message): bool
    {
        // Nigerian (+234), international, and common spacing/dash patterns
        // Requires at least 7 consecutive digits (prevents false positives on years, scores)
        return (bool) preg_match(
            '/(?:\+?234|0)[789]\d[\s\-]?\d{3}[\s\-]?\d{4}|'  // Nigerian mobile
            . '\+?\d{1,3}[\s\-]?\(?\d{2,4}\)?[\s\-]?\d{3,4}[\s\-]?\d{3,4}|' // International
            . '\b\d{5,}[\s\-]\d{4,}\b/',                                       // Long digit sequences split by space/dash
            $message
        );
    }

    private function containsEmail(string $message): bool
    {
        return (bool) preg_match('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $message);
    }

    private function containsWhatsApp(string $message): bool
    {
        // "WhatsApp me on", "Ping me on WA", "wa.me/", "chat me on whatsapp"
        return (bool) preg_match(
            '/\b(?:whatsapp|wa\.me|ping\s+me|text\s+me\s+on\s+wa|message\s+me\s+on\s+wa)\b/i',
            $message
        );
    }

    private function containsSocialHandle(string $message): bool
    {
        // @handle patterns, Instagram/Twitter/TikTok/Snapchat/Telegram mentions
        return (bool) preg_match(
            '/(?:^|[\s,]|find\s+me\s+on|dm\s+me\s+on|my\s+(?:ig|insta|twitter|telegram|snap))[\s:]*@[a-zA-Z0-9._]{2,}|'
            . '\b(?:instagram|twitter|telegram|snapchat|tiktok)(?:\s+handle|\s+is|\s*:|\s+@)[^\s]{2,}/i',
            $message
        );
    }
}
