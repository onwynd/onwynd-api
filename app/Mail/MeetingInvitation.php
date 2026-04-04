<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class MeetingInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $organizer;

    public $title;

    public $date;

    public $time;

    public $location;

    public $agenda;

    public $acceptLink;

    public $declineLink;

    public function __construct($organizer, $title, $date, $time, $location, $agenda, $acceptLink, $declineLink)
    {
        $this->organizer = $organizer;
        $this->title = $title;
        $this->date = $date;
        $this->time = $time;
        $this->location = $location;
        $this->agenda = $agenda;
        $this->acceptLink = $acceptLink;
        $this->declineLink = $declineLink;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitation: '.$this->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.workplace.meeting-invitation',
        );
    }

    public function build()
    {
        // Build ICS calendar invite attachment
        $start = Carbon::parse($this->date.' '.$this->time)->tz('UTC');
        $end = $start->copy()->addHour();

        $uid = uniqid('onwynd-', true).'@onwynd.com';
        $dtStamp = Carbon::now('UTC')->format('Ymd\THis\Z');
        $dtStart = $start->format('Ymd\THis\Z');
        $dtEnd = $end->format('Ymd\THis\Z');

        $ics = "BEGIN:VCALENDAR\r\n".
               "PRODID:-//Onwynd//Meeting Invitation//EN\r\n".
               "VERSION:2.0\r\n".
               "CALSCALE:GREGORIAN\r\n".
               "METHOD:REQUEST\r\n".
               "BEGIN:VEVENT\r\n".
               "UID:$uid\r\n".
               "DTSTAMP:$dtStamp\r\n".
               "DTSTART:$dtStart\r\n".
               "DTEND:$dtEnd\r\n".
               "SUMMARY:{$this->title}\r\n".
               "DESCRIPTION:{$this->agenda}\r\n".
               "LOCATION:{$this->location}\r\n".
               "ORGANIZER;CN={$this->organizer}:mailto:{$this->organizer}\r\n".
               "END:VEVENT\r\n".
               "END:VCALENDAR\r\n";

        return $this->subject('Invitation: '.$this->title)
            ->view('emails.workplace.meeting-invitation')
            ->attachData($ics, 'invite.ics', [
                'mime' => 'text/calendar; charset=utf-8; method=REQUEST',
            ]);
    }
}
