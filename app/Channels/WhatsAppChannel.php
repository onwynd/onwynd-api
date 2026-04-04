<?php

namespace App\Channels;

use App\Services\WhatsAppService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Laravel notification channel for WhatsApp.
 * Notifications opt in by returning 'whatsapp' from via() and implementing toWhatsApp().
 */
class WhatsAppChannel
{
    public function __construct(protected WhatsAppService $whatsapp) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $phone = $notifiable->phone ?? null;
        if (empty($phone)) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);
        if (empty($message)) {
            return;
        }

        try {
            $this->whatsapp->send($phone, $message);
        } catch (\Throwable $e) {
            Log::warning('WhatsAppChannel: send failed', [
                'notifiable_id' => $notifiable->id ?? null,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
