<?php

namespace App\Repositories\Contracts;

interface TicketRepositoryInterface
{
    public function getUserTickets($userId);

    public function createTicket(array $data);

    public function addMessage($ticketId, $userId, $message);

    public function updateStatus($ticketId, $status);

    public function getAllTickets(); // Admin
}
