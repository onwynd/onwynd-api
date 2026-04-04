<?php

namespace App\Notifications;

use App\Models\Gamification\Badge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BadgeAwarded extends Notification implements ShouldQueue
{
    use Queueable;

    protected $badge;

    /**
     * Create a new notification instance.
     */
    public function __construct(Badge $badge)
    {
        $this->badge = $badge;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Badge Awarded: '.$this->badge->name)
            ->line('Congratulations! You have earned a new badge.')
            ->line('Badge: '.$this->badge->name)
            ->line('Description: '.$this->badge->description)
            ->action('View My Badges', url('/dashboard/badges'))
            ->line('Keep up the great work!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'badge_id' => $this->badge->id,
            'name' => $this->badge->name,
            'description' => $this->badge->description,
            'icon_url' => $this->badge->icon_url,
            'message' => 'You earned the '.$this->badge->name.' badge!',
        ];
    }
}
