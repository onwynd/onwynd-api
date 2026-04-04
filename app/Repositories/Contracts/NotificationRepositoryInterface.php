<?php

namespace App\Repositories\Contracts;

interface NotificationRepositoryInterface
{
    public function getUserNotifications($userId);

    public function markAsRead($notificationId);

    public function sendToUser($userId, $title, $body, $type = 'info');
}
