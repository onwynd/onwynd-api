<?php

namespace App\Http\Controllers\API\V1\Sales;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SalesNotificationController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        // For now, return an empty array of notifications
        // In a real application, you would fetch sales-specific notifications here
        return $this->sendResponse([], 'Sales notifications retrieved successfully.');
    }
}
