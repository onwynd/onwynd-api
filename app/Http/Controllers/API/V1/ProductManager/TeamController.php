<?php

namespace App\Http\Controllers\API\V1\ProductManager;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use Illuminate\Http\Request;

class TeamController extends BaseController
{
    /**
     * Display a listing of the product team members.
     */
    public function index(Request $request)
    {
        // Get users who are product managers, tech team, or admins
        $query = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['product_manager', 'tech_team', 'admin', 'clinical_advisor']);
        });

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $team = $query->with('roles')->paginate(20);

        return $this->sendResponse($team, 'Product team members retrieved successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $user = User::with(['roles', 'profile'])->find($id);

        if (! $user) {
            return $this->sendError('Team member not found.');
        }

        return $this->sendResponse($user, 'Team member details retrieved successfully.');
    }
}
