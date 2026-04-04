<?php

namespace App\Listeners;

use App\Events\MessageSent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class BroadcastMessageSent implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(MessageSent $event): void
    {
        // Log for audit/debugging
        Log::info('Message broadcasted', [
            'message_id' => $event->message->id,
            'sender_id' => $event->message->sender_id,
            'type' => $event->message->type,
            'channel' => $event->message->chatable_type
                ? "chat.{$event->message->chatable_type}.{$event->message->chatable_id}"
                : "dm.{$event->message->receiver_id}",
        ]);

        // Here you could trigger push notifications (FCM/APNs)
        // or update unread counts in Redis
    }
}
