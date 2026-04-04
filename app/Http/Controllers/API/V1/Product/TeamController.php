<?php

namespace App\Http\Controllers\API\V1\Product;

use App\Http\Controllers\API\BaseController;
use App\Models\User;

class TeamController extends BaseController
{
    public function index()
    {
        // For now, return all users or filter by role if roles are set up
        // Assuming 'product_manager', 'developer', 'designer' roles exist
        // Or just return a mock list if User model isn't fully ready with roles for this context

        $users = User::take(10)->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first() ?? 'Member',
                'avatar' => 'https://ui-avatars.com/api/?name='.urlencode($user->name),
                'status' => 'active',
            ];
        });

        return $this->sendResponse($users, 'Team members retrieved successfully.');
    }
}
