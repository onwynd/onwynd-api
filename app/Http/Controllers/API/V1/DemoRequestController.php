<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Mail\DemoRequestAlertEmail;
use App\Mail\DemoRequestConfirmationEmail;
use App\Models\CalendarEvent;
use App\Models\Lead;
use App\Services\Admin\AdminNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class DemoRequestController extends BaseController
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name'   => 'required|string|max:255',
            'contact_name'   => 'required|string|max:255',
            'email'          => 'required|email|max:255',
            'phone'          => 'nullable|string|max:20',
            'company_size'   => 'nullable|string',
            'message'        => 'nullable|string',
            'org_type'       => 'required|in:corporate,university',
            'preferred_date' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Create Lead
        $email = strtolower(trim((string) $request->email));
        $contactName = trim((string) $request->contact_name);
        $nameParts = preg_split('/\s+/', $contactName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstName = $nameParts[0] ?? $contactName;
        $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '';

        $noteStamp = now()->format('Y-m-d H:i');
        $noteChunk = $noteStamp."\n".
            "source: demo_form\n".
            "org_type: {$request->org_type}\n".
            "company_size: {$request->company_size}\n\n".
            ((string) ($request->message ?? ''));

        $lead = Lead::where('email', $email)->first();
        if ($lead) {
            $existingNotes = $lead->notes ? $lead->notes."\n\n" : '';
            $mergedNotes = $existingNotes.$noteChunk;
            if (strlen($mergedNotes) > 20000) {
                $mergedNotes = substr($mergedNotes, -20000);
            }

            $lead->update([
                'company' => $lead->company ?: $request->company_name,
                'first_name' => $lead->first_name ?: $firstName,
                'last_name' => $lead->last_name ?: $lastName,
                'phone' => $lead->phone ?: $request->phone,
                'status' => $lead->status ?: 'new',
                'source' => $lead->source ?: 'demo_form',
                'notes' => $mergedNotes,
            ]);
        } else {
            $lead = Lead::create([
                'company' => $request->company_name,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $request->phone,
                'status' => 'new',
                'source' => 'demo_form',
                'owner_id' => null,
                'notes' => $noteChunk,
            ]);
        }

        // Create a pending calendar event visible to all founders/leadership
        try {
            $start = $request->filled('preferred_date')
                ? Carbon::parse($request->preferred_date)->setHour(10)->setMinute(0)->setSecond(0)
                : Carbon::now()->addBusinessDays(1)->setHour(10)->setMinute(0)->setSecond(0);

            CalendarEvent::create([
                'title'            => "Demo Request: {$lead->company}",
                'description'      => "{$lead->first_name} {$lead->last_name} · {$lead->email}" .
                                      ($request->message ? "\n\n{$request->message}" : ''),
                'start_time'       => $start,
                'end_time'         => $start->copy()->addMinutes(30),
                'type'             => 'demo',
                'status'           => 'pending',
                'lead_id'          => $lead->id,
                'created_by'       => null,
                'participants'     => array_filter([$lead->email]),
                'visible_to_roles' => ['super_admin', 'admin', 'ceo', 'coo'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Demo calendar event creation failed', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);
        }

        // Bell notification + internal alert emails to leadership/sales
        AdminNotificationService::newDemoRequest($lead, $request->org_type);

        // Branded confirmation to the requester
        try {
            $confirmationMail = new DemoRequestConfirmationEmail(
                contactName: $contactName,
                companyName: (string) $request->company_name,
                orgType:     (string) $request->org_type,
            );

            if (config('mail.queue_enabled', false)) {
                Mail::to($email)->queue($confirmationMail);
            } else {
                Mail::to($email)->send($confirmationMail);
            }

            Log::channel('mail')->info('Demo confirmation sent', [
                'email'   => $email,
                'company' => $request->company_name,
            ]);
        } catch (\Throwable $e) {
            Log::channel('mail')->warning('Demo confirmation failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Request received',
        ]);
    }
}
