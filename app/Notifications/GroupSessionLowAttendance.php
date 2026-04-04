<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class GroupSessionLowAttendance extends BaseNotification
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
            ->subject('Low Attendance Warning: '.$this->groupSession->title)
            ->line('The group session "'.$this->groupSession->title.'" has low attendance.')
            ->line('Current participants: '.$this->groupSession->participants()->count())
            ->action('View Session', url('/groups/'.$this->groupSession->id));
    }

    public function toArray($notifiable)
    {
        return [
            'group_session_id' => $this->groupSession->id,
            'title' => 'Low Attendance',
            'message' => 'Group session has low attendance.',
            'type' => 'group_session_low_attendance',
        ];
    }
}
