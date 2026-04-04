<?php

namespace App\Http\Controllers\API\V1\Institutional;

use App\Http\Controllers\API\BaseController;
use App\Mail\InstitutionalEmployeeWelcomeEmail;
use App\Mail\OrganizationInviteMail;
use App\Models\Institutional\Organization;
use App\Models\Institutional\OrganizationInvite;
use App\Models\Institutional\OrganizationMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InviteController extends BaseController
{
    /**
     * GET /invites/{token}
     *
     * Returns invite metadata (org name, email) so the frontend can pre-fill
     * the registration form. Does NOT expose PII beyond the invite's email.
     */
    public function show(string $token)
    {
        $invite = OrganizationInvite::with('organization')
            ->where('token', $token)
            ->first();

        if (! $invite) {
            return $this->sendError('Invite not found.', [], 404);
        }

        if (! $invite->isPending()) {
            $reason = $invite->accepted_at ? 'already been accepted' : 'expired';

            return $this->sendError("This invite has {$reason}.", [], 410);
        }

        return $this->sendResponse([
            'organization' => [
                'id' => $invite->organization->id,
                'name' => $invite->organization->name,
            ],
            'email' => $invite->email,
        ], 'Invite is valid.');
    }

    /**
     * POST /institutional/organizations/{organization}/invites
     *
     * Generates a signed invite link for a single employee email.
     * Protected by role:institutional|admin + institutional.paywall.
     */
    public function send(Request $request, Organization $organization)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'role' => 'in:member,admin',
            'department' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Guard: authenticated user must belong to this org (or be admin)
        $user = $request->user();
        $isAdmin = $user->hasRole('admin');
        if (! $isAdmin) {
            $membership = OrganizationMember::where('organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->first();

            if (! $membership) {
                return $this->sendError('Forbidden.', [], 403);
            }
        }

        $email = strtolower(trim($request->input('email')));

        // Check for an existing pending invite for this email + org
        $existing = OrganizationInvite::where('organization_id', $organization->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return $this->sendResponse(
                ['invite_token' => $existing->token],
                'A pending invite already exists for this email.',
            );
        }

        $token = Str::random(40);

        $invite = OrganizationInvite::create([
            'organization_id' => $organization->id,
            'email' => $email,
            'token' => $token,
            'role' => $request->input('role', 'member'),
            'department' => $request->input('department'),
            'expires_at' => now()->addDays(7),
            'invited_by' => $user->id,
        ]);

        Mail::to($email)->queue(new OrganizationInviteMail($invite, $organization));

        return $this->sendResponse(
            ['invite_token' => $invite->token],
            'Invite created successfully.',
        );
    }

    /**
     * POST /invites/{token}/accept
     *
     * Public route — called by the employee after registering or signing in.
     * Expects: { first_name, last_name, password } if registering,
     *          or just auth token in Authorization header if already logged in.
     */
    public function accept(Request $request, string $token)
    {
        $invite = OrganizationInvite::with('organization')
            ->where('token', $token)
            ->first();

        if (! $invite) {
            return $this->sendError('Invite not found.', [], 404);
        }

        if (! $invite->isPending()) {
            $reason = $invite->accepted_at ? 'already been accepted' : 'expired';

            return $this->sendError("This invite has {$reason}.", [], 410);
        }

        // Case A: authenticated user accepting
        if ($request->user()) {
            $user = $request->user();
        } else {
            // Case B: new registration — requires name + password
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation Error.', $validator->errors(), 422);
            }

            // Check if email already registered
            $existingUser = User::where('email', $invite->email)->first();
            if ($existingUser) {
                return $this->sendError(
                    'An account with this email already exists. Please sign in first.',
                    ['email' => $invite->email],
                    409,
                );
            }

            $patientRole = Role::where('slug', 'patient')
                ->orWhere('name', 'patient')
                ->first();

            $user = User::create([
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'email' => $invite->email,
                'password' => Hash::make($request->input('password')),
                'role_id' => $patientRole?->id,
                'email_verified_at' => now(), // org-invited employees skip email verification
            ]);
        }

        // Prevent double-linking
        $alreadyMember = OrganizationMember::where('organization_id', $invite->organization_id)
            ->where('user_id', $user->id)
            ->exists();

        if (! $alreadyMember) {
            $planConfig = $this->planSessionConfig($invite->organization->subscription_plan ?? 'starter');

            OrganizationMember::create([
                'organization_id' => $invite->organization_id,
                'user_id' => $user->id,
                'role' => $invite->role,
                'department' => $invite->department,
                'sessions_limit' => $planConfig['sessions_per_month'] ?? 0,
                'session_duration_minutes' => $planConfig['session_duration_minutes'],
                'sessions_used_this_month' => 0,
                'last_reset_at' => now(),
            ]);
        }

        // Mark invite as accepted
        $invite->update(['accepted_at' => now()]);

        // Send institutional employee welcome email (only for new registrations)
        if (! $request->user()) {
            try {
                $loginUrl = rtrim(config('frontend.url'), '/') . '/auth/signin';
                Mail::to($user->email)->queue(new InstitutionalEmployeeWelcomeEmail(
                    name:            trim("{$user->first_name} {$user->last_name}"),
                    role:            $invite->role ?? 'employee',
                    institutionName: $invite->organization->name,
                    loginUrl:        $loginUrl,
                    institutionLogo: $invite->organization->logo ?? null,
                ));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Institutional employee welcome email failed', [
                    'user_id'  => $user->id,
                    'org_id'   => $invite->organization_id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        // Issue a Sanctum token for the new user (if they just registered)
        $authToken = $request->user() ? null : $user->createToken('invite-accept')->plainTextToken;

        return $this->sendResponse([
            'organization' => [
                'id' => $invite->organization->id,
                'name' => $invite->organization->name,
            ],
            'already_member' => $alreadyMember,
            'auth_token' => $authToken,
        ], 'Invite accepted successfully.');
    }

    private function planSessionConfig(string $plan): array
    {
        return match ($plan) {
            'growth' => ['sessions_per_month' => 3, 'session_duration_minutes' => 35],
            'enterprise' => ['sessions_per_month' => null, 'session_duration_minutes' => 35],
            default => ['sessions_per_month' => 0, 'session_duration_minutes' => null],
        };
    }
}
