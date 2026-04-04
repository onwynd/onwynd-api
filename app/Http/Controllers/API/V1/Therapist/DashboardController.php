<?php

namespace App\Http\Controllers\API\V1\Therapist;

use App\Http\Controllers\API\BaseController;
use App\Repositories\Contracts\TherapyRepositoryInterface;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    protected $therapyRepository;

    public function __construct(TherapyRepositoryInterface $therapyRepository)
    {
        $this->therapyRepository = $therapyRepository;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $stats = $this->therapyRepository->getTherapistStats($user->id);

        if ($user->therapistProfile) {
            $stats['rating'] = $user->therapistProfile->rating_average;
            $stats['total_sessions_completed'] = $user->therapistProfile->total_sessions;
        }

        return $this->sendResponse($stats, 'Therapist dashboard data retrieved successfully.');
    }
}
