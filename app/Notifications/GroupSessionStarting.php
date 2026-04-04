<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class GroupSessionStarting extends BaseNotification
{
    protected string $preferenceKey = 'session_reminders';

    public $groupSession;

    public function __construct($groupSession)
    {
        $this->groupSession = $groupSession;
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Join Now: '.$this->groupSession->title)
            ->line('Your group session "'.$this->groupSession->title.'" is starting now.')
            ->action('Join Group Session', url('/groups/'.$this->groupSession->id))
            ->line('Don\'t miss out!');
    }

    public function toArray($notifiable)
    {
        return [
            'group_session_id' => $this->groupSession->id,
            'title' => 'Group Session Starting',
            'message' => 'Your group session is starting now.',
            'type' => 'group_session_starting',
        ];
    }
}
