<?php

namespace App\Http\Controllers\API\V1\Legal;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;

class DashboardController extends BaseController
{
    public function stats(Request $request)
    {
        return $this->sendResponse([
            'open_cases' => 0,
            'closed_cases' => 0,
            'pending_review' => 0,
            'due_this_week' => 0,
        ], 'Legal stats retrieved.');
    }

    public function cases(Request $request)
    {
        return $this->sendResponse([], 'Legal cases retrieved.');
    }

    public function showCase(Request $request, $id)
    {
        return $this->sendResponse(null, 'Legal case retrieved.');
    }
}
