<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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

        // Firebase / social login user — has firebase_uid and no manually-set password
        if ($user->firebase_uid && str_ends_with($user->email, '@anonymous.onwynd.com') === false) {
            return $this->sendResponse(
                ['status' => 'social_login'],
                'This account was created with Google. Sign in with Google instead.'
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
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET
                    ? $this->sendResponse([], __($status))
                    : $this->sendError(__($status));
    }
}
