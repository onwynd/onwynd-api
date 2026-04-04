<?php

namespace App\Notifications;

use App\Models\GroupSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class GroupSessionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $session;

    protected $type;

    protected $data;

    /**
     * Create a new notification instance.
     *
     * Types: creation, invite, reminder_24h, reminder_1h, live, low_attendance, follow_up, payment_success
     */
    public function __construct(GroupSession $session, string $type, array $data = [])
    {
        $this->session = $session;
        $this->type = $type;
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = ['database'];

        if (in_array($this->type, ['creation', 'invite', 'reminder_24h', 'payment_success'])) {
            $channels[] = 'mail';
        }

        // Push notifications would be added here if configured

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->getSubject())
            ->greeting('Hello '.($notifiable->first_name ?? 'Participant').'!');

        switch ($this->type) {
            case 'invite':
                $mail->line('You have been invited to a '.Str::headline($this->session->session_type).' therapy session: '.$this->session->title)
                    ->action('Join Session', url('/group-therapy/join/'.$this->session->uuid.'?invite_token='.($this->data['invite_token'] ?? '')))
                    ->line('Scheduled for: '.$this->session->scheduled_at->format('M d, Y @ H:i'));
                break;
            case 'reminder_24h':
                $mail->line("Reminder: Your group therapy session '".$this->session->title."' starts in 24 hours.")
                    ->action('View Details', url('/group-therapy/'.$this->session->uuid))
                    ->line('Scheduled for: '.$this->session->scheduled_at->format('M d, Y @ H:i'));
                break;
            case 'payment_success':
                $mail->line("Payment successful! Your seat for '".$this->session->title."' is confirmed.")
                    ->line('Amount paid: '.($this->data['amount'] ?? '0'))
                    ->action('View Session', url('/group-therapy/'.$this->session->uuid));
                break;
            default:
                $mail->line('Update regarding your group therapy session: '.$this->session->title)
                    ->action('View Details', url('/group-therapy/'.$this->session->uuid));
        }

        return $mail;
    }

    protected function getSubject(): string
    {
        return match ($this->type) {
            'creation' => 'New Group Session Assigned: '.$this->session->title,
            'invite' => 'Invitation: Group Therapy Session',
            'reminder_24h' => 'Reminder: 24 Hours Until Session',
            'reminder_1h' => 'Urgent: Session Starts in 1 Hour',
            'live' => 'Session is LIVE: '.$this->session->title,
            'payment_success' => 'Payment Confirmed - Group Therapy',
            default => 'Update: Group Therapy Session',
        };
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'session_uuid' => $this->session->uuid,
            'title' => $this->session->title,
            'type' => $this->type,
            'message' => $this->getMessage(),
            'scheduled_at' => $this->session->scheduled_at,
        ];
    }

    protected function getMessage(): string
    {
        return match ($this->type) {
            'creation' => 'A new group session has been created for you.',
            'invite' => 'You have been invited to a group session.',
            'reminder_24h' => 'Your session starts in 24 hours.',
            'reminder_1h' => 'Your session starts in 1 hour.',
            'live' => 'The session has started. Join now!',
            'low_attendance' => 'Warning: Only '.($this->data['count'] ?? 0).' participants have joined so far.',
            'follow_up' => 'The session has ended. Share your thoughts.',
            'payment_success' => 'Your payment for the group session was successful.',
            default => 'Update regarding your group therapy session.',
        };
    }
}
