<?php

namespace App\Http\Controllers\API\V1\Support;

use App\Http\Controllers\API\BaseController;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TicketController extends BaseController
{
    public function index(Request $request)
    {
        $query = SupportTicket::with(['user:id,first_name,last_name,email', 'assignedAgent:id,first_name,last_name']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('assigned_to')) {
            if ($request->assigned_to === 'me') {
                $query->where('assigned_to', $request->user()->id);
            } elseif ($request->assigned_to === 'unassigned') {
                $query->whereNull('assigned_to');
            }
        }

        $tickets = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->sendResponse($tickets, 'Tickets retrieved successfully.');
    }

    public function show($id)
    {
        $ticket = SupportTicket::with(['user', 'assignedAgent', 'messages.user:id,first_name,last_name,profile_photo'])
            ->find($id);

        if (! $ticket) {
            return $this->sendError('Ticket not found.');
        }

        return $this->sendResponse($ticket, 'Ticket details retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $ticket = SupportTicket::find($id);

        if (! $ticket) {
            return $this->sendError('Ticket not found.');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:open,in_progress,resolved,closed',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        if ($request->has('status')) {
            $ticket->status = $request->status;
            if ($request->status === 'resolved') {
                $ticket->resolved_at = now();
            }
        }

        if ($request->has('priority')) {
            $ticket->priority = $request->priority;
        }

        if ($request->has('assigned_to')) {
            $ticket->assigned_to = $request->assigned_to;
        }

        $ticket->save();

        return $this->sendResponse($ticket, 'Ticket updated successfully.');
    }

    public function reply(Request $request, $id)
    {
        $ticket = SupportTicket::find($id);

        if (! $ticket) {
            return $this->sendError('Ticket not found.');
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'is_internal' => 'boolean',
            'attachments' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->message,
            'is_internal' => $request->boolean('is_internal', false),
            'attachments' => $request->attachments,
        ]);

        $ticket->touch(); // Update updated_at
        $ticket->last_response_at = now();
        $ticket->save();

        return $this->sendResponse($message->load('user:id,first_name,last_name,profile_photo'), 'Reply added successfully.');
    }
}
