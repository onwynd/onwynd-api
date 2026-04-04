<?php

namespace App\Mail;

use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class JobApplicationConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public JobApplication $application;

    public JobPosting $job;

    /**
     * Create a new message instance.
     */
    public function __construct(JobApplication $application, JobPosting $job)
    {
        $this->application = $application;
        $this->job = $job;
        $this->subject('Application Received - '.$job->title.' at Onwynd');
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->view('emails.careers.application-confirmation')
            ->with([
                'applicantName' => $this->application->first_name.' '.$this->application->last_name,
                'jobTitle' => $this->job->title,
                'jobDepartment' => $this->job->department,
                'jobLocation' => $this->job->location,
                'applicationDate' => $this->application->created_at->format('F j, Y'),
                'applicationId' => $this->application->uuid,
            ]);
    }
}
