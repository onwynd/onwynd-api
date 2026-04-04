<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\CalendarEvent;
use App\Models\Lead;
use App\Models\User;
use App\Services\Admin\AdminNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DemoLeadController extends BaseController
{
    /**
     * List demo leads (source=demo_form).
     */
    public function index(Request $request)
    {
        $leads = Lead::with(['assignedUser'])
            ->where('source', 'demo_form')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->sendResponse($leads, 'Demo leads retrieved.');
    }

    /**
     * Assign a demo lead to a sales rep and optionally schedule the call.
     *
     * POST /api/v1/admin/leads/{lead}/assign-demo
     * Body:
     *  - user_id      (required) — sales rep to assign
     *  - scheduled_at (optional) — ISO datetime for the call
     *  - meeting_link (optional)
     *  - notes        (optional)
     */
    public function assignDemo(Request $request, Lead $lead)
    {
        $validator = Validator::make($request->all(), [
            'user_id'      => 'required|integer|exists:users,id',
            'scheduled_at' => 'nullable|date',
            'meeting_link' => 'nullable|url|max:1000',
            'notes'        => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $salesRep = User::findOrFail($request->user_id);
        $assigner = Auth::user();

        // Update lead assignment
        $lead->update([
            'assigned_to'   => $salesRep->id,
            'status'        => $lead->status === 'new' ? 'contacted' : $lead->status,
            'handoff_note'  => $request->notes ?: "Assigned by {$assigner->first_name} {$assigner->last_name}",
            'handed_off_at' => now(),
            'handed_off_by' => $assigner->id,
        ]);

        $calendarEvent = null;

        // Create/update calendar event if a time was scheduled
        if ($request->filled('scheduled_at')) {
            $start = Carbon::parse($request->scheduled_at);
            $end   = $start->copy()->addMinutes(30);

            // Find existing pending demo event for this lead, or create a new one
            $calendarEvent = CalendarEvent::where('lead_id', $lead->id)
                ->where('type', 'demo')
                ->first();

            $attrs = [
                'title'            => "Demo: {$lead->company} ({$lead->first_name} {$lead->last_name})",
                'start_time'       => $start,
                'end_time'         => $end,
                'type'             => 'demo',
                'status'           => 'confirmed',
                'lead_id'          => $lead->id,
                'assigned_to'      => $salesRep->id,
                'meeting_link'     => $request->meeting_link,
                'notes'            => $request->notes,
                'participants'     => array_filter([$lead->email, $salesRep->email]),
                'visible_to_roles' => ['super_admin', 'admin', 'ceo', 'coo'],
            ];

            if ($calendarEvent) {
                $calendarEvent->update($attrs);
            } else {
                $attrs['created_by'] = $assigner->id;
                $calendarEvent = CalendarEvent::create($attrs);
            }
        }

        // Bell-notify the assigned sales rep
        AdminNotificationService::demoAssigned($lead, $salesRep, $assigner, $calendarEvent);

        return $this->sendResponse([
            'lead'           => $lead->fresh(['assignedUser']),
            'calendar_event' => $calendarEvent,
        ], 'Demo lead assigned successfully.');
    }
}
