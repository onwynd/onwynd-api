<?php

namespace App\Http\Controllers\API\V1\Institutional;

use App\Http\Controllers\API\BaseController;

class InstitutionalReferralController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Mock data
        $referrals = [
            [
                'id' => 1,
                'patientName' => 'Alice Johnson',
                'program' => 'Anxiety Management',
                'status' => 'Active',
                'date' => '2024-03-15',
                'doctorName' => 'Dr. Sarah Smith',
            ],
            [
                'id' => 2,
                'patientName' => 'Bob Brown',
                'program' => 'Depression Support',
                'status' => 'Pending',
                'date' => '2024-03-14',
                'doctorName' => 'Dr. Mike Jones',
            ],
            [
                'id' => 3,
                'patientName' => 'Charlie Davis',
                'program' => 'Stress Reduction',
                'status' => 'Completed',
                'date' => '2024-03-10',
                'doctorName' => 'Dr. Emily White',
            ],
        ];

        return $this->sendResponse($referrals, 'Referrals retrieved successfully.');
    }
}
