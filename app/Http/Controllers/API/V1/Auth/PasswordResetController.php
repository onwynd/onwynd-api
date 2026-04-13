<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRules;

class PasswordResetController extends BaseController
{
    public function sendResetLinkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $email = strtolower(trim($request->string('email')->toString()));
        $user  = User::where('email', $email)->first();

        // Email not registered at all
        if (! $user) {
            return $this->sendResponse(
                ['status' => 'not_found'],
                'No account found with this email.'
            );
        }

        // Anonymous / guest user — no real password set
        if ($user->is_anonymous) {
            return $this->sendResponse(
                ['status' => 'anonymous'],
                'This email was used for a guest session. Create a full account to set a password.'
            );
        }

        // Pure social users (Google, phone) never had an email password — block reset.
        // Firebase email/password users (auth_provider = 'email') CAN reset to establish
        // a direct API password while Firebase is suspended.
        $isSocialOnly = $user->firebase_uid
            && in_array($user->auth_provider ?? '', ['google', 'phone', 'anonymous'], true);

        if ($isSocialOnly) {
            return $this->sendResponse(
                ['status' => 'social_login'],
                'This account uses social sign-in (Google/phone). Password reset is not available.'
            );
        }

        // Normal email/password account — send reset link
        $status = Password::sendResetLink(['email' => $email]);

        return $status === Password::RESET_LINK_SENT
            ? $this->sendResponse(['status' => 'sent'], __($status))
            : $this->sendError(__($status));
    }

    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRules::min(8)],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password'     => Hash::make($password),
                    'has_password' => true,
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
                    ? $this->sendResponse([], __($status))
                    : $this->sendError(__($status));
    }
}
