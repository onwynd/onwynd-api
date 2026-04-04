<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class EmailVerificationController extends BaseController
{
    public function verify(Request $request, $id, $hash)
    {
        $user = User::find($id);

        if (! $user) {
            $frontend = rtrim(env('FRONTEND_URL', Config::get('app.url')), '/');

            return redirect($frontend.'/login?verify=not_found');
        }

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            $frontend = rtrim(env('FRONTEND_URL', Config::get('app.url')), '/');

            return redirect($frontend.'/login?verify=invalid');
        }

        if ($user->hasVerifiedEmail()) {
            $frontend = rtrim(env('FRONTEND_URL', Config::get('app.url')), '/');

            return redirect($frontend.'/dashboard?verified=already');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $frontend = rtrim(env('FRONTEND_URL', Config::get('app.url')), '/');

        return redirect($frontend.'/dashboard?verified=success');
    }

    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->sendError('Email already verified.', [], 400);
        }

        $request->user()->sendEmailVerificationNotification();

        return $this->sendResponse([], 'Verification link sent!');
    }
}
