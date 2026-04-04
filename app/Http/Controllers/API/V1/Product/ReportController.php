<?php

namespace App\Http\Controllers\API\V1\Product;

use App\Http\Controllers\API\BaseController;

class ReportController extends BaseController
{
    public function index()
    {
        $reports = [
            [
                'id' => 1,
                'title' => 'Q1 Product Performance',
                'date' => '2024-03-31',
                'author' => 'System',
                'status' => 'ready',
                'type' => 'performance',
            ],
            [
                'id' => 2,
                'title' => 'User Feedback Summary - March',
                'date' => '2024-03-15',
                'author' => 'Sarah Product',
                'status' => 'draft',
                'type' => 'feedback',
            ],
            [
                'id' => 3,
                'title' => 'Feature Usage Analysis',
                'date' => '2024-02-28',
                'author' => 'System',
                'status' => 'ready',
                'type' => 'usage',
            ],
        ];

        return $this->sendResponse($reports, 'Reports retrieved.');
    }

    public function show($id)
    {
        return $this->sendResponse([
            'id' => $id,
            'content' => 'Report content placeholder...',
            'url' => '#',
        ], 'Report details retrieved.');
    }
}
