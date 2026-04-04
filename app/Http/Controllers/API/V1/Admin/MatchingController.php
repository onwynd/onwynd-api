<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Services\PlatformSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin toggle for the 3-state therapist regional matching algorithm.
 */
class MatchingController extends BaseController
{
    public function getState(): JsonResponse
    {
        return $this->sendResponse([
            'state'       => PlatformSettingsService::get('regional_matching_state', 'on'),
            'allowed'     => ['on', 'conditional', 'off'],
            'description' => [
                'on'          => 'Hard regional filter — Nigerian IP sees Nigeria-available therapists; international sees international.',
                'conditional' => 'Regional first; expands to language-only match if no regional therapist available.',
                'off'         => 'All therapists shown regardless of region (language filter always applied).',
            ],
        ], 'Regional matching state retrieved.');
    }

    public function setState(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'state' => 'required|in:on,conditional,off',
        ]);

        PlatformSettingsService::set('regional_matching_state', $validated['state']);

        return $this->sendResponse([
            'state' => $validated['state'],
        ], 'Regional matching state updated.');
    }
}
