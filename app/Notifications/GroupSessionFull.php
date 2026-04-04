<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class GroupSessionFull extends BaseNotification
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
            ->subject('Group Session Full: '.$this->groupSession->title)
            ->line('The group session "'.$this->groupSession->title.'" has reached maximum capacity.')
            ->action('View Session', url('/groups/'.$this->groupSession->id));
    }

    public function toArray($notifiable)
    {
        return [
            'group_session_id' => $this->groupSession->id,
            'title' => 'Group Session Full',
            'message' => 'The session '.$this->groupSession->title.' is now full.',
            'type' => 'group_session_full',
        ];
    }
}
