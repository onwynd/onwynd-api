<?php

namespace App\Repositories\Eloquent;

use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Repositories\Contracts\TicketRepositoryInterface;

class TicketRepository implements TicketRepositoryInterface
{
    public function getUserTickets($userId)
    {
        return SupportTicket::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function createTicket(array $data)
    {
        return SupportTicket::create($data);
    }

    public function addMessage($ticketId, $userId, $message)
    {
        return TicketMessage::create([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'message' => $message,
        ]);
    }

    public function updateStatus($ticketId, $status)
    {
        $ticket = SupportTicket::find($ticketId);
        if ($ticket) {
            $ticket->update(['status' => $status]);

            return $ticket;
        }

        return null;
    }

    public function getAllTickets()
    {
        return SupportTicket::with('user')->orderBy('created_at', 'desc')->get();
    }
}
