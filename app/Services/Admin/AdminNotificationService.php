<?php

namespace App\Services\Admin;

use App\Mail\DemoRequestAlertEmail;
use App\Mail\WaitlistLeadAlertEmail;
use App\Models\CalendarEvent;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\User;
use App\Models\WaitlistSubmission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Pushes real-time notifications to all admin users.
 * Notifications are created in the shared `notifications` table
 * targeted at each admin's user_id so the bell component fetches them.
 */
class AdminNotificationService
{
    /**
     * Notify all admins about a key event.
     *
     * @param  string  $type     Maps to the icon in the bell component (e.g. 'waitlist', 'contact', 'security', 'signup')
     * @param  string  $title
     * @param  string  $message
     * @param  array   $data     Extra JSON context (e.g. ['id' => 5, 'email' => '...'])
     * @param  string|null  $actionUrl   Deep-link in the dashboard
     */
    public static function notifyAdmins(
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?string $actionUrl = null
    ): void {
        try {
            // Fetch admin user IDs (cached 5 min to avoid per-event DB hits)
            $adminIds = Cache::remember('admin_user_ids', 300, function () {
                return User::whereIn('role', ['admin', 'super_admin', 'support'])
                    ->pluck('id')
                    ->toArray();
            });

            foreach ($adminIds as $adminId) {
                Notification::create([
                    'user_id'    => $adminId,
                    'type'       => $type,
                    'title'      => $title,
                    'message'    => $message,
                    'data'       => array_merge($data, ['action_url' => $actionUrl]),
                    'action_url' => $actionUrl,
                    'is_read'    => false,
                ]);
            }
        } catch (\Throwable $e) {
            // Never let notification failures break the main flow
            Log::warning('AdminNotificationService failed', [
                'error' => $e->getMessage(),
                'type'  => $type,
            ]);
        }
    }

    // ── Typed helpers ────────────────────────────────────────────────────────

    public static function newWaitlistSignup(WaitlistSubmission $submission): void
    {
        $name = "{$submission->first_name} {$submission->last_name}";

        // Institution → high priority: bell all admins + email super_admins
        // Therapist   → medium priority: bell all admins + email admins
        // Patient/Other → standard: bell all admins only
        $isHighPriority   = $submission->role === 'institution';
        $isMediumPriority = $submission->role === 'therapist';

        $orgSuffix = $submission->organization_name ? " ({$submission->organization_name})" : '';
        $title     = $isHighPriority
            ? "🔴 Institution lead: {$name}{$orgSuffix}"
            : ($isMediumPriority ? "🟡 Therapist signup: {$name}" : "New waitlist signup: {$name}");

        self::notifyAdmins(
            type:      'waitlist',
            title:     $title,
            message:   "{$submission->email} joined as {$submission->role}",
            data:      ['email' => $submission->email, 'role' => $submission->role],
            actionUrl: '/admin/waitlist',
        );

        // Email alerts for high/medium priority leads
        if ($isHighPriority || $isMediumPriority) {
            $recipientRoles = $isHighPriority
                ? ['super_admin']          // CEO / founders only for institutions
                : ['super_admin', 'admin']; // All admins for therapists

            try {
                $recipients = User::whereIn('role', $recipientRoles)
                    ->whereNotNull('email')
                    ->get();

                foreach ($recipients as $recipient) {
                    Mail::to($recipient->email)
                        ->send(new WaitlistLeadAlertEmail($submission, $recipient->first_name ?? $recipient->name ?? 'there'));
                }
            } catch (\Throwable $e) {
                Log::channel('mail')->warning('Waitlist lead alert email failed', [
                    'submission_id' => $submission->id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Demo request submitted via public site.
     * Bell-notifies all admins; emails branded alert to super_admin + admin + sales roles.
     */
    public static function newDemoRequest(Lead $lead, string $orgType): void
    {
        $company = $lead->company;
        $orgLabel = $orgType === 'university' ? 'University' : 'Corporate';

        self::notifyAdmins(
            type:      'demo',
            title:     "🔴 Demo request: {$company} ({$orgLabel})",
            message:   "{$lead->first_name} {$lead->last_name} · {$lead->email}",
            data:      ['lead_id' => $lead->id, 'org_type' => $orgType],
            actionUrl: "/sales/leads/{$lead->id}",
        );

        // Email branded alert to leadership + sales
        try {
            $recipients = User::whereIn('role', ['super_admin', 'admin', 'sales'])
                ->whereNotNull('email')
                ->get();

            foreach ($recipients as $recipient) {
                $name = $recipient->first_name ?? $recipient->name ?? 'there';
                $mailable = new DemoRequestAlertEmail(
                    lead:         $lead,
                    recipientName: $name,
                    orgType:      $orgType,
                    companySize:  $lead->notes ? '' : '',  // size is stored in notes
                    message:      '',
                );

                if (config('mail.queue_enabled', false)) {
                    Mail::to($recipient->email)->queue($mailable);
                } else {
                    Mail::to($recipient->email)->send($mailable);
                }
            }

            Log::channel('mail')->info('Demo alert sent', [
                'lead_id'    => $lead->id,
                'recipients' => $recipients->pluck('email')->toArray(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('mail')->warning('Demo alert email failed', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public static function newContactSubmission(string $name, string $email, string $subject, string $ticketId): void
    {
        self::notifyAdmins(
            type:      'contact',
            title:     "Contact form: {$subject}",
            message:   "{$name} ({$email}) — ref #{$ticketId}",
            data:      ['email' => $email, 'ticket_id' => $ticketId],
            actionUrl: '/admin/contact',
        );
    }

    public static function newUserSignup(string $name, string $email, string $role): void
    {
        self::notifyAdmins(
            type:      'signup',
            title:     "New user signed up: {$name}",
            message:   "{$email} registered as {$role}",
            data:      ['email' => $email, 'role' => $role],
            actionUrl: '/admin/users',
        );
    }

    public static function newFeedback(string $type, int $rating = 0, string $excerpt = ''): void
    {
        $stars = $rating > 0 ? " ({$rating}/5)" : '';
        self::notifyAdmins(
            type:      'feedback',
            title:     "New {$type} feedback{$stars}",
            message:   $excerpt ?: 'No message provided',
            data:      ['feedback_type' => $type, 'rating' => $rating],
            actionUrl: '/admin/feedback',
        );
    }

    /**
     * Notify a sales rep that a demo lead has been assigned to them.
     */
    public static function demoAssigned(Lead $lead, User $salesRep, User $assigner, ?CalendarEvent $event = null): void
    {
        $when = $event ? $event->start_time->format('D j M, g:ia') : 'TBD';

        // Bell-notify the sales rep
        try {
            Notification::create([
                'user_id'    => $salesRep->id,
                'type'       => 'demo',
                'title'      => "📅 Demo assigned: {$lead->company}",
                'message'    => "{$lead->first_name} {$lead->last_name} · {$lead->email} · {$when}",
                'data'       => ['lead_id' => $lead->id, 'event_id' => $event?->id],
                'action_url' => "/sales/calendar",
                'is_read'    => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('demoAssigned notification failed', ['error' => $e->getMessage()]);
        }

        // Also bell-notify all admins/CEO/COO so they know the assignment happened
        $assignerName = trim("{$assigner->first_name} {$assigner->last_name}");
        self::notifyAdmins(
            type:      'demo',
            title:     "✅ Demo assigned to {$salesRep->first_name} {$salesRep->last_name}",
            message:   "{$lead->company} · {$when} · by {$assignerName}",
            data:      ['lead_id' => $lead->id, 'assigned_to' => $salesRep->id],
            actionUrl: "/admin/demo-leads",
        );
    }

    public static function newVulnerabilityReport(string $trackingNumber, string $severity, string $category): void
    {
        $severityLabel = strtoupper($severity);
        self::notifyAdmins(
            type:      'security',
            title:     "🚨 [{$severityLabel}] Vulnerability report #{$trackingNumber}",
            message:   "Category: {$category}",
            data:      ['tracking_number' => $trackingNumber, 'severity' => $severity],
            actionUrl: '/admin/security/reports',
        );
    }
}
