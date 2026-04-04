<?php

namespace App\Repositories\Eloquent;

use App\Models\TherapySession;
use App\Repositories\Contracts\SessionRepositoryInterface;

class SessionRepository implements SessionRepositoryInterface
{
    public function all()
    {
        return TherapySession::all();
    }

    public function find($id)
    {
        return TherapySession::find($id);
    }

    public function create(array $data)
    {
        return TherapySession::create($data);
    }

    public function update($id, array $data)
    {
        $session = TherapySession::find($id);
        if ($session) {
            $session->update($data);

            return $session;
        }

        return null;
    }

    public function delete($id)
    {
        return TherapySession::destroy($id);
    }

    public function getUpcomingForUser($userId)
    {
        return TherapySession::where('user_id', $userId)
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at', 'asc')
            ->get();
    }

    public function getPastForUser($userId)
    {
        return TherapySession::where('user_id', $userId)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at', 'desc')
            ->get();
    }

    public function getForTherapist($therapistId)
    {
        return TherapySession::where('therapist_id', $therapistId)
            ->orderBy('scheduled_at', 'desc')
            ->get();
    }
}
