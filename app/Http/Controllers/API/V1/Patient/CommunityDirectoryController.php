<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\Community;
use App\Models\CommunityMembership;
use App\Services\Community\CommunityService;
use Illuminate\Http\Request;

class CommunityDirectoryController extends BaseController
{
    protected CommunityService $service;

    public function __construct(CommunityService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $communities = $this->service->list([
            'search' => $request->get('search'),
            'category' => $request->get('category'),
            'is_private' => $request->get('is_private'),
            'per_page' => $request->get('per_page', 12),
        ]);

        return $this->sendResponse($communities, 'Communities retrieved successfully.');
    }

    public function show($identifier)
    {
        $community = Community::where('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->first();
        if (! $community) {
            return $this->sendError('Community not found.', [], 404);
        }

        return $this->sendResponse($community, 'Community retrieved successfully.');
    }

    public function join(Request $request, $identifier)
    {
        $community = Community::where('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->first();
        if (! $community) {
            return $this->sendError('Community not found.', [], 404);
        }
        $membership = CommunityMembership::where('community_id', $community->id)
            ->where('user_id', $request->user()->id)
            ->first();
        if ($membership) {
            if ($membership->status !== 'active') {
                $membership->update([
                    'status' => 'active',
                    'joined_at' => now(),
                    'left_at' => null,
                ]);
            }
        } else {
            $membership = CommunityMembership::create([
                'community_id' => $community->id,
                'user_id' => $request->user()->id,
                'role' => 'member',
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        return $this->sendResponse($membership, 'Joined community.');
    }

    public function leave(Request $request, $identifier)
    {
        $community = Community::where('uuid', $identifier)
            ->orWhere('slug', $identifier)
            ->first();
        if (! $community) {
            return $this->sendError('Community not found.', [], 404);
        }
        $membership = CommunityMembership::where('community_id', $community->id)
            ->where('user_id', $request->user()->id)
            ->first();
        if (! $membership) {
            return $this->sendError('Membership not found.', [], 404);
        }
        $membership->update([
            'status' => 'left',
            'left_at' => now(),
        ]);

        return $this->sendResponse($membership, 'Left community.');
    }
}
