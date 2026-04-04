<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class GroupSessionBooked extends BaseNotification
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
            ->subject('Group Session Booked: '.$this->groupSession->title)
            ->line('Your seat for the group session has been reserved.')
            ->line('Session: '.$this->groupSession->title)
            ->line('Time: '.$this->groupSession->scheduled_at->format('M d, Y H:i'))
            ->action('View Group Session', url('/groups/'))
            ->line('Thank you for joining Onwynd Community!');
    }

    public function toArray($notifiable)
    {
        return [
            'group_session_id' => $this->groupSession->id,
            'title' => 'Group Session Booked',
            'message' => 'Your seat for '.$this->groupSession->title.' is reserved.',
            'type' => 'group_session_booked',
        ];
    }
}
