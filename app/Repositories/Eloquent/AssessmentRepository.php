<?php

namespace App\Repositories\Eloquent;

use App\Models\Assessment;
use App\Models\UserAssessmentResult;
use App\Repositories\Contracts\AssessmentRepositoryInterface;

class AssessmentRepository implements AssessmentRepositoryInterface
{
    public function all()
    {
        return Assessment::where('is_active', true)->get();
    }

    public function find($id)
    {
        return Assessment::with('questions')->find($id);
    }

    public function create(array $data)
    {
        return Assessment::create($data);
    }

    public function getUserResults($userId)
    {
        return UserAssessmentResult::with('assessment')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function assignToUser($assessmentId, $userId)
    {
        // Logic to assign assessment if needed, or just return the assessment
        // For now, we assume user can take any active assessment
        return Assessment::find($assessmentId);
    }
}
