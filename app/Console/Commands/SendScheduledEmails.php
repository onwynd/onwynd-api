<?php

namespace App\Console\Commands;

use App\Models\Mail\ScheduledEmail;
// use App\Services\Notification\EmailService;  // Not implemented yet
// use App\Enums\EmailStatus;  // Not implemented yet
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendScheduledEmails extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'emails:send-scheduled 
                            {--retry-failed : Retry previously failed emails}
                            {--limit=50 : Maximum emails to send in one batch}
                            {--verbose : Show detailed output}';

    /**
     * The console command description.
     */
    protected $description = 'Send emails scheduled for specific times or delays';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $emailService = app('EmailService');
        $limit = (int) $this->option('limit');
        $retryFailed = $this->option('retry-failed');
        $verbose = $this->option('verbose');

        $this->info('📧 Processing scheduled emails...');
        $this->newLine();

        try {
            // 1. Find emails ready to send
            $query = ScheduledEmail::where('status', 'pending')
                ->where('scheduled_at', '<=', now())
                ->orderBy('scheduled_at', 'asc')
                ->limit($limit);

            if ($retryFailed) {
                // Also include failed emails that haven't exceeded retry limit
                $query = ScheduledEmail::where(function ($q) {
                    $q->where('status', 'pending')
                        ->where('scheduled_at', '<=', now())
                        ->orWhere(function ($q2) {
                            $q2->where('status', 'pending')
                                ->where('attempts', '<', 3)
                                ->where('last_attempted_at', '<=', now()->subHours(1));
                        });
                })
                    ->orderBy('scheduled_at', 'asc')
                    ->limit($limit);
            }

            $scheduledEmails = $query->get();

            if ($scheduledEmails->isEmpty()) {
                $this->info('✅ No scheduled emails to send at this time');

                return self::SUCCESS;
            }

            $this->info("📨 Found {$scheduledEmails->count()} emails to send");
            $bar = $this->output->createProgressBar($scheduledEmails->count());

            $successCount = 0;
            $failureCount = 0;

            foreach ($scheduledEmails as $email) {
                try {
                    // Prepare email content
                    $data = json_decode($email->data, true) ?? [];

                    // Send using appropriate mailable class
                    Mail::to($email->recipient_email)
                        ->send($this->getMailable($email->email_type, $data));

                    // Mark as sent
                    $email->update([
                        'status' => 'pending',
                        'sent_at' => now(),
                        'attempts' => $email->attempts + 1,
                    ]);

                    if ($verbose) {
                        $this->line("✅ Sent to {$email->recipient_email} (Type: {$email->email_type})");
                    }

                    $successCount++;
                } catch (\Exception $e) {
                    $attempts = $email->attempts + 1;

                    // Update failure info
                    $email->update([
                        'status' => $attempts >= 3 ? 'pending' : 'pending',
                        'last_attempted_at' => now(),
                        'attempts' => $attempts,
                        'last_error' => $e->getMessage(),
                    ]);

                    if ($verbose) {
                        $this->line("❌ Failed to send to {$email->recipient_email}: {$e->getMessage()}");
                    }

                    $failureCount++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            // 2. Find emails scheduled for future times and prepare queue
            $futureEmails = ScheduledEmail::where('status', 'pending')
                ->where('scheduled_at', '>', now())
                ->count();

            // 3. Generate summary report
            $this->info('📊 Email Sending Summary:');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Successfully sent', $successCount],
                    ['Failed attempts', $failureCount],
                    ['Pending in queue', $futureEmails],
                    ['Total processed', $scheduledEmails->count()],
                ]
            );

            // 4. Log statistics
            $stats = [
                'sent' => $successCount,
                'failed' => $failureCount,
                'pending' => $futureEmails,
                'processed' => $scheduledEmails->count(),
                'timestamp' => now()->toIso8601String(),
            ];

            Log::info('Scheduled emails processed', $stats);

            $this->info('✅ Scheduled email processing completed');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error processing scheduled emails: {$e->getMessage()}");
            Log::error('Scheduled email processing failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Get appropriate mailable class based on email type
     */
    private function getMailable(string $type, array $data)
    {
        return match ($type) {
            'welcome' => new \App\Mail\WelcomeEmail(
                $data['name'] ?? 'User',
                $data['login_url'] ?? url('/login')
            ),
            'password_reset' => new \App\Mail\PasswordResetEmail(
                $data['url'] ?? url('/password/reset')
            ),
            'confirm_email' => new \App\Mail\ConfirmEmail(
                $data['url'] ?? url('/email/verify')
            ),
            'invoice' => new \App\Mail\InvoiceEmail(
                $data['name'] ?? 'User',
                $data['items'] ?? [],
                $data['tax'] ?? 0,
                $data['total'] ?? 0,
                $data['status_url'] ?? null
            ),
            'subscription_cancelled' => new \App\Mail\SubscriptionCancelledEmail(
                $data['feedback_url'] ?? null
            ),
            'trial_expired' => new \App\Mail\TrialExpiredEmail(
                $data['billing_url'] ?? url('/billing'),
                $data['extension_url'] ?? null
            ),
            'appointment_confirmation' => new \App\Mail\AppointmentBookingConfirmation(
                $data['patient_name'] ?? 'User',
                $data['therapist_name'] ?? 'Therapist',
                $data['date_time'] ?? now()->toDayDateTimeString(),
                $data['link'] ?? url('/patient/sessions')
            ),
            'appointment_reminder' => new \App\Mail\AppointmentReminder(
                $data['patient_name'] ?? 'User',
                $data['date_time'] ?? now()->toDayDateTimeString(),
                $data['link'] ?? url('/patient/sessions')
            ),
            'new_message' => new \App\Mail\NewMessageNotification(
                $data['user_name'] ?? 'User',
                $data['sender_name'] ?? 'Someone',
                $data['message_preview'] ?? 'You have a new message.',
                $data['link'] ?? url('/chat')
            ),
            'therapist_verification' => new \App\Mail\TherapistVerificationStatus(
                $data['therapist_name'] ?? 'Therapist',
                $data['status'] ?? 'pending',
                $data['reason'] ?? null,
                $data['link'] ?? url('/therapist/dashboard')
            ),
            'payout_processed' => new \App\Mail\PayoutProcessed(
                $data['therapist_name'] ?? 'Therapist',
                $data['amount'] ?? '$0.00',
                $data['date'] ?? now()->toDateString(),
                $data['transaction_id'] ?? 'N/A'
            ),
            'leave_request_status' => new \App\Mail\LeaveRequestStatus(
                $data['employee_name'] ?? 'Employee',
                $data['status'] ?? 'pending',
                $data['leave_type'] ?? 'Annual',
                $data['dates'] ?? 'N/A',
                $data['manager_name'] ?? 'Manager',
                $data['reason'] ?? null
            ),
            'payroll_notification' => new \App\Mail\PayrollNotification(
                $data['employee_name'] ?? 'Employee',
                $data['month'] ?? now()->format('F Y'),
                $data['net_amount'] ?? '$0.00',
                $data['link'] ?? url('/hr/payroll')
            ),
            'task_assigned' => new \App\Mail\TaskAssigned(
                $data['assignee_name'] ?? 'User',
                $data['task_title'] ?? 'New Task',
                $data['project_name'] ?? 'Project',
                $data['due_date'] ?? 'TBD',
                $data['assigner_name'] ?? 'Manager',
                $data['link'] ?? url('/tasks')
            ),
            'ticket_status_update' => new \App\Mail\TicketStatusUpdate(
                $data['user_name'] ?? 'User',
                $data['ticket_id'] ?? '000',
                $data['status'] ?? 'updated',
                $data['link'] ?? url('/support')
            ),
            'system_alert' => new \App\Mail\SystemAlert(
                $data['admin_name'] ?? 'Admin',
                $data['alert_level'] ?? 'info',
                $data['message_body'] ?? 'System notification.',
                $data['timestamp'] ?? now()->toDateTimeString()
            ),
            'founder_welcome' => new \App\Mail\FoundersWelcome(
                $data['name'] ?? 'User'
            ),
            'session_summary' => new \App\Mail\SessionSummary(
                $data['patient_name'] ?? 'User',
                $data['therapist_name'] ?? 'Therapist',
                $data['session_date'] ?? now()->toFormattedDateString(),
                $data['summary'] ?? null,
                $data['recommendations'] ?? [],
                $data['homework'] ?? [],
                $data['dashboard_link'] ?? url('/patient/dashboard')
            ),
            'weekly_admin_report' => new \App\Mail\WeeklyAdminReport(
                $data['start_date'] ?? now()->startOfWeek()->toFormattedDateString(),
                $data['end_date'] ?? now()->endOfWeek()->toFormattedDateString(),
                $data['metrics'] ?? [
                    'new_users' => 0,
                    'user_growth' => 0,
                    'revenue' => '$0',
                    'revenue_growth' => 0,
                    'active_therapists' => 0,
                    'sessions_held' => 0,
                ],
                $data['ai_analysis'] ?? 'No data available for analysis.',
                $data['forecast'] ?? ['projected_revenue' => '$0', 'projected_users' => 0],
                $data['action_steps'] ?? ['Review dashboard']
            ),
            'meeting_invitation' => new \App\Mail\MeetingInvitation(
                $data['organizer'] ?? 'Organizer',
                $data['title'] ?? 'Meeting',
                $data['date'] ?? now()->toDateString(),
                $data['time'] ?? '10:00 AM',
                $data['location'] ?? 'Virtual',
                $data['agenda'] ?? null,
                $data['accept_link'] ?? url('/calendar/accept'),
                $data['decline_link'] ?? url('/calendar/decline')
            ),
            'document_shared' => new \App\Mail\DocumentShared(
                $data['name'] ?? 'User',
                $data['sharer_name'] ?? 'Colleague',
                $data['document_name'] ?? 'Document',
                $data['file_type'] ?? 'PDF',
                $data['file_size'] ?? '1MB',
                $data['message'] ?? null,
                $data['link'] ?? url('/documents')
            ),
            'project_update' => new \App\Mail\ProjectUpdate(
                $data['name'] ?? 'User',
                $data['project_name'] ?? 'Project',
                $data['update_message'] ?? 'Update available.',
                $data['updater_name'] ?? 'Manager',
                $data['timestamp'] ?? now()->toDateTimeString(),
                $data['next_steps'] ?? [],
                $data['project_link'] ?? url('/projects')
            ),
            'ambassador_welcome' => new \App\Mail\AmbassadorWelcome(
                $data['name'] ?? 'Ambassador',
                $data['referral_code'] ?? 'CODE123',
                $data['dashboard_link'] ?? url('/ambassador')
            ),
            'referral_reward' => new \App\Mail\ReferralReward(
                $data['referred_name'] ?? 'New User',
                $data['reward_amount'] ?? '$50',
                $data['reward_type'] ?? 'Cash Credit',
                $data['rewards_link'] ?? url('/rewards')
            ),
            default => new \App\Mail\WelcomeEmail(
                $data['name'] ?? 'User',
                $data['login_url'] ?? url('/login')
            ), // Fallback
        };
    }
}
