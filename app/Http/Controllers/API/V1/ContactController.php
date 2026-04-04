<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Mail\ContactFormSubmitted;
use App\Models\ContactSubmission;
use App\Models\Lead;
use App\Services\Admin\AdminNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ContactController extends BaseController
{
    /**
     * Public contact form submission used by marketing/contact page.
     */
    public function submit(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'subject' => 'required|string|in:general,support,partnerships,press,other',
            'message' => 'required|string|min:10',
            'phone' => 'nullable|string|max:30',
        ]);

        // generate a public-facing ticket/reference id
        $ticketId = 'ONW-' . strtoupper(Str::random(6));

        $email = strtolower(trim($data['email']));
        $name = trim($data['name']);
        $nameParts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstName = $nameParts[0] ?? $name;
        $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : null;

        $lead = Lead::where('email', $email)->first();
        $noteStamp = now()->format('Y-m-d H:i');
        $noteChunk = $noteStamp."\n".
            "source: contact_form\n".
            "ticket_id: {$ticketId}\n".
            "subject: {$data['subject']}\n\n".
            $data['message'];

        if ($lead) {
            $existingNotes = $lead->notes ? $lead->notes."\n\n" : '';
            $mergedNotes = $existingNotes.$noteChunk;
            if (strlen($mergedNotes) > 20000) {
                $mergedNotes = substr($mergedNotes, -20000);
            }

            $lead->update([
                'first_name' => $lead->first_name ?: $firstName,
                'last_name' => $lead->last_name ?: ($lastName ?? ''),
                'phone' => $lead->phone ?: ($data['phone'] ?? null),
                'status' => $lead->status ?: 'new',
                'source' => $lead->source ?: 'contact_form',
                'notes' => $mergedNotes,
            ]);
        } else {
            $lead = Lead::create([
                'first_name' => $firstName,
                'last_name' => $lastName ?? '',
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'company' => null,
                'status' => 'new',
                'source' => 'contact_form',
                'notes' => $noteChunk,
            ]);
        }

        $submission = ContactSubmission::create([
            'ticket_id' => $ticketId,
            'name'      => $data['name'],
            'email'     => $email,
            'phone'     => $data['phone'] ?? null,
            'subject'   => $data['subject'],
            'message'   => $data['message'],
            'status'    => 'new',
        ]);

        // Notify admins
        AdminNotificationService::newContactSubmission($data['name'], $data['email'], $data['subject'], $ticketId);

        // queue notification to support team (non-blocking)
        try {
            Mail::to(config('app.support_email', 'support@onwynd.com'))
                ->queue(new ContactFormSubmitted($submission, $data, $ticketId));
        } catch (\Throwable $e) {
            // don't fail the public API if mail delivery/queue is misconfigured
            report($e);
        }

        return $this->sendResponse([
            'ticket_id' => $ticketId,
            'reference_number' => $ticketId,
        ], 'Thanks — your message has been received.');
    }

    /**
     * Public contact information (used by marketing site)
     */
    public function info()
    {
        return $this->sendResponse([
            'email' => 'support@onwynd.com',
            'phone' => '+1 (555) 123-4567',
            'address' => '123 Mental Health Way, San Francisco, CA 94102',
            'office_hours' => 'Mon-Fri, 9am-6pm PST',
            'social_media' => [
                'twitter' => 'https://twitter.com/onwynd',
                'instagram' => 'https://instagram.com/onwynd',
            ],
        ], 'Contact information retrieved.');
    }

    /**
     * Subscribe an email to newsletter (creates a lightweight lead record).
     */
    public function subscribeNewsletter(Request $request)
    {
        $payload = $request->validate([
            'email' => 'required|email|max:191',
        ]);

        app(\App\Services\Marketing\NewsletterService::class)->subscribe($payload['email']);

        return $this->sendResponse(['message' => 'If your email is valid, a confirmation message has been sent.'], 'Subscribed successfully.');
    }
}
