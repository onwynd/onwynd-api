<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Models\CalendarEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CalendarEventController extends BaseController
{
    /**
     * List calendar events visible to the authenticated user.
     *
     * An event is visible when:
     *  - Its visible_to_roles includes the user's role, OR
     *  - Its assigned_to equals the user's id, OR
     *  - The user is super_admin or admin (sees everything)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $user->role ?? 'user';

        $query = CalendarEvent::with(['lead', 'assignee'])
            ->when($request->filled('start'), fn ($q) =>
                $q->where('start_time', '>=', Carbon::parse($request->start)->startOfDay()))
            ->when($request->filled('end'), fn ($q) =>
                $q->where('end_time', '<=', Carbon::parse($request->end)->endOfDay()))
            ->when($request->filled('type'), fn ($q) =>
                $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) =>
                $q->where('status', $request->status));

        // Role-based visibility
        if (! in_array($role, ['super_admin', 'admin'])) {
            $query->where(function ($q) use ($user, $role) {
                $q->whereJsonContains('visible_to_roles', $role)
                  ->orWhere('assigned_to', $user->id)
                  ->orWhere('created_by', $user->id);
            });
        }

        $events = $query->orderBy('start_time')->get()->map(fn ($e) => $this->format($e));

        return $this->sendResponse($events, 'Calendar events retrieved.');
    }

    /**
     * Create a new calendar event.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'             => 'required|string|max:255',
            // Accept full datetime OR date + time strings (frontend sends date + HH:mm separately)
            'start_time'        => 'nullable|string',
            'end_time'          => 'nullable|string',
            'date'              => 'nullable|date',
            'type'              => 'nullable|string|max:50',
            'status'            => 'nullable|string|in:pending,confirmed,cancelled',
            'description'       => 'nullable|string',
            'lead_id'           => 'nullable|integer|exists:leads,id',
            'assigned_to'       => 'nullable|integer|exists:users,id',
            'participants'      => 'nullable|array',
            'meeting_link'      => 'nullable|string|max:1000',
            'notes'             => 'nullable|string',
            'visible_to_roles'  => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Merge date + time strings into full Carbon datetimes
        $date      = $request->input('date', now()->toDateString());
        $startRaw  = $request->input('start_time', '10:00');
        $endRaw    = $request->input('end_time', '10:30');
        $startTime = strlen($startRaw) <= 5
            ? Carbon::parse("{$date} {$startRaw}")
            : Carbon::parse($startRaw);
        $endTime = strlen($endRaw) <= 5
            ? Carbon::parse("{$date} {$endRaw}")
            : Carbon::parse($endRaw);

        $event = CalendarEvent::create([
            'title'            => $request->title,
            'description'      => $request->description,
            'start_time'       => $startTime,
            'end_time'         => $endTime,
            'type'             => $request->type ?? 'meeting',
            'status'           => $request->status ?? 'pending',
            'lead_id'          => $request->lead_id,
            'created_by'       => Auth::id(),
            'assigned_to'      => $request->assigned_to,
            'participants'     => $request->participants ?? [],
            'meeting_link'     => $request->meeting_link,
            'notes'            => $request->notes,
            'visible_to_roles' => $request->visible_to_roles ?? [],
        ]);

        return $this->sendResponse($this->format($event->load(['lead', 'assignee'])), 'Event created.', 201);
    }

    /**
     * Update an existing event.
     */
    public function update(Request $request, CalendarEvent $calendarEvent)
    {
        $validator = Validator::make($request->all(), [
            'title'            => 'sometimes|string|max:255',
            'start_time'       => 'sometimes|date',
            'end_time'         => 'sometimes|date',
            'type'             => 'sometimes|string|max:50',
            'status'           => 'sometimes|string|in:pending,confirmed,cancelled',
            'description'      => 'nullable|string',
            'assigned_to'      => 'nullable|integer|exists:users,id',
            'participants'     => 'nullable|array',
            'participants.*'   => 'email',
            'meeting_link'     => 'nullable|url|max:1000',
            'notes'            => 'nullable|string',
            'visible_to_roles' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $calendarEvent->update(array_filter($request->only([
            'title', 'description', 'start_time', 'end_time', 'type', 'status',
            'assigned_to', 'participants', 'meeting_link', 'notes', 'visible_to_roles',
        ]), fn ($v) => ! is_null($v)));

        return $this->sendResponse($this->format($calendarEvent->fresh(['lead', 'assignee'])), 'Event updated.');
    }

    /**
     * Delete an event.
     */
    public function destroy(CalendarEvent $calendarEvent)
    {
        $calendarEvent->delete();
        return $this->sendResponse([], 'Event deleted.');
    }

    // ── Format ────────────────────────────────────────────────────────────────

    private function format(CalendarEvent $e): array
    {
        return [
            'id'               => $e->id,
            'title'            => $e->title,
            'description'      => $e->description,
            'startTime'        => $e->start_time?->toIso8601String(),
            'endTime'          => $e->end_time?->toIso8601String(),
            'date'             => $e->start_time?->toDateString(),
            'type'             => $e->type,
            'status'           => $e->status,
            'meetingLink'      => $e->meeting_link,
            'participants'     => $e->participants ?? [],
            'notes'            => $e->notes,
            'leadId'           => $e->lead_id,
            'leadCompany'      => $e->lead?->company,
            'leadName'         => $e->lead ? trim("{$e->lead->first_name} {$e->lead->last_name}") : null,
            'assignedTo'       => $e->assigned_to,
            'assigneeName'     => $e->assignee ? trim("{$e->assignee->first_name} {$e->assignee->last_name}") : null,
            'visibleToRoles'   => $e->visible_to_roles ?? [],
            'createdAt'        => $e->created_at?->toIso8601String(),
        ];
    }
}
