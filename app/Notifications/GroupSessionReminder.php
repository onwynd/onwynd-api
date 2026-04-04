<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class GroupSessionReminder extends BaseNotification
{
    protected string $preferenceKey = 'session_reminders';

    public $groupSession;

    public $type; // 24h or 1h

    public function __construct($groupSession, $type = 'reminder')
    {
        $this->groupSession = $groupSession;
        $this->type = $type;
    }

    public function toMail($notifiable)
    {
        $timeText = $this->type === 'reminder_24h' ? '24 hours' : '1 hour';

        return (new MailMessage)
            ->subject('Group Session Reminder: '.$this->groupSession->title)
            ->line('Your group session "'.$this->groupSession->title.'" starts in '.$timeText.'.')
            ->action('View Details', url('/groups/'))
            ->line('See you there!');
    }

    public function toArray($notifiable)
    {
        return [
            'group_session_id' => $this->groupSession->id,
            'title' => 'Group Session Reminder',
            'message' => 'Your group session starts soon.',
            'type' => 'group_session_'.$this->type,
        ];
    }
}
