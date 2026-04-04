<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailLog extends Model
{
    protected $fillable = [
        'mailable_class',
        'recipient',
        'subject',
        'status',
        'failure_reason',
        'metadata',
        'sent_at',
        'failed_at',
    ];

    protected $casts = [
        'metadata'  => 'array',
        'sent_at'   => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected $appends = ['mailable_name', 'purpose'];

    /** Human-readable mail type name */
    public function getPurposeAttribute(): string
    {
        $map = [
            'WaitlistConfirmationEmail' => 'Waitlist Confirmation',
            'WaitlistInviteEmail'       => 'Waitlist Invite',
            'ContactFormSubmitted'      => 'Contact Form',
            'FoundersWelcome'           => 'Founders Welcome',
            'WelcomeEmail'              => 'Welcome Email',
            'PasswordReset'             => 'Password Reset',
            'SessionConfirmation'       => 'Session Confirmation',
            'PaymentConfirmation'       => 'Payment Confirmation',
            'AssessmentResult'          => 'Assessment Result',
        ];
        return $map[$this->mailable_name] ?? $this->mailable_name;
    }

    /** Short class name without namespace, e.g. "WaitlistInviteEmail" */
    public function getMailableNameAttribute(): string
    {
        if (! $this->mailable_class) {
            return '—';
        }
        $parts = explode('\\', $this->mailable_class);
        return end($parts);
    }
}
