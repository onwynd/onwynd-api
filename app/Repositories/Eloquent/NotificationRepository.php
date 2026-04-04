<?php

namespace App\Repositories\Eloquent;

use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function getUserNotifications($userId)
    {
        return Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function markAsRead($notificationId)
    {
        $notification = Notification::find($notificationId);
        if ($notification) {
            $notification->update(['read_at' => now()]);

            return true;
        }

        return false;
    }

    public function sendToUser($userId, $title, $body, $type = 'info')
    {
        return Notification::create([
            'user_id' => $userId,
            'title'   => $title,
            'message' => $body,   // fixed: was 'body' which is not in fillable
            'type'    => $type,
            'is_read' => false,
        ]);
    }
}
