<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Services\AI\DailyTipService;
use Illuminate\Http\Request;

class DailyTipController extends BaseController
{
    protected $dailyTipService;

    public function __construct(DailyTipService $dailyTipService)
    {
        $this->dailyTipService = $dailyTipService;
    }

    /**
     * Get today's daily tip
     */
    public function getTodayTip(Request $request)
    {
        $tip = $this->dailyTipService->getTodayTip();

        if (! $tip) {
            return $this->sendError('No tip available for today', 404);
        }

        // Increment usage count
        $tip->incrementUsage();

        return $this->sendResponse([
            'tip' => $tip->tip,
            'category' => $tip->category,
            'technique' => $tip->technique,
            'metadata' => $tip->metadata,
            'uuid' => $tip->uuid,
            'created_at' => $tip->created_at,
        ], 'Daily tip retrieved successfully.');
    }

    /**
     * Get tips by category
     */
    public function getTipsByCategory(Request $request, string $category)
    {
        $limit = $request->input('limit', 10);
        $tips = $this->dailyTipService->getTipsByCategory($category, $limit);

        return $this->sendResponse([
            'tips' => $tips,
            'category' => $category,
            'count' => count($tips),
        ], 'Tips retrieved successfully.');
    }

    /**
     * Generate a new tip (admin function)
     */
    public function generateTip(Request $request)
    {
        $this->authorize('admin');

        $category = $request->input('category');
        $tip = $this->dailyTipService->generateNewTip($category);

        if (! $tip) {
            return $this->sendError('Failed to generate tip', 500);
        }

        return $this->sendResponse([
            'tip' => $tip->tip,
            'category' => $tip->category,
            'technique' => $tip->technique,
            'metadata' => $tip->metadata,
            'uuid' => $tip->uuid,
        ], 'Tip generated successfully.');
    }

    /**
     * Regenerate today's tip (admin function)
     */
    public function regenerateTodayTip(Request $request)
    {
        $this->authorize('admin');

        $tip = $this->dailyTipService->regenerateTodayTip();

        if (! $tip) {
            return $this->sendError('Failed to regenerate tip', 500);
        }

        return $this->sendResponse([
            'tip' => $tip->tip,
            'category' => $tip->category,
            'technique' => $tip->technique,
            'metadata' => $tip->metadata,
            'uuid' => $tip->uuid,
        ], 'Today\'s tip regenerated successfully.');
    }
}
