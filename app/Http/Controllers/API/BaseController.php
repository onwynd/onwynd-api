<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cookie\Factory as CookieFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class BaseController extends Controller
{
    public function sendResponse($result, $message, $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $result,
        ];

        return response()->json($response, $code);
    }

    protected function deviceTokenName(string $userAgent = ''): string
    {
        $ua = $userAgent ?: '';
        if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad'))  return 'iOS';
        if (str_contains($ua, 'Android'))                               return 'Android';
        if (str_contains($ua, 'Windows'))                               return 'Windows';
        if (str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS X')) return 'macOS';
        if (str_contains($ua, 'Linux'))                                 return 'Linux';
        return 'Unknown Device';
    }

    public function sendError($error, $errorMessages = [], $code = 404): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if (! empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }

        return response()->json($response, $code);
    }

    protected function authCookieDomain(Request $request): ?string
    {
        $host = $request->getHost();

        if ($host === 'onwynd.com' || str_ends_with($host, '.onwynd.com')) {
            return '.onwynd.com';
        }

        return null;
    }

    protected function makeAuthCookie(Request $request, string $token): Cookie
    {
        return app(CookieFactory::class)->make(
            'auth_token',
            $token,
            60 * 24 * 30,
            '/',
            $this->authCookieDomain($request),
            $request->isSecure(),
            true,
            false,
            $request->isSecure() ? 'none' : 'lax',
        );
    }

    protected function makeExpiredAuthCookie(Request $request): Cookie
    {
        return app(CookieFactory::class)->forget(
            'auth_token',
            '/',
            $this->authCookieDomain($request),
        );
    }
}
