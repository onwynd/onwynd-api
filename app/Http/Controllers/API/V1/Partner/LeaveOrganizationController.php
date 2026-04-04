<?php

namespace App\Http\Controllers\API\V1\Partner;

use App\Http\Controllers\API\BaseController;
use App\Models\PartnerUser;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveOrganizationController extends BaseController
{
    /**
     * POST /api/v1/partner/leave-organization
     *
     * Remove the authenticated partner user from their organization
     * and downgrade their role to 'customer'.
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();

        DB::transaction(function () use ($user) {
            // Remove all PartnerUser pivot rows for this user
            PartnerUser::where('user_id', $user->id)->delete();

            // Downgrade role to 'customer' (regular user)
            $customerRole = Role::where('slug', 'customer')->first();
            if ($customerRole) {
                $user->role_id = $customerRole->id;
                $user->save();
            }
        });

        return $this->sendResponse([], 'You have successfully left your organization. Your account is now a regular user account.');
    }
}
