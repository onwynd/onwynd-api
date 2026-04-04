<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReferralReward extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $referredName;

    public $rewardAmount;

    public $rewardType;

    public $rewardsLink;

    public function __construct($referredName, $rewardAmount, $rewardType, $rewardsLink)
    {
        $this->referredName = $referredName;
        $this->rewardAmount = $rewardAmount;
        $this->rewardType = $rewardType;
        $this->rewardsLink = $rewardsLink;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'ve Earned a Reward! 🎉',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.engagement.referral-reward',
        );
    }
}
