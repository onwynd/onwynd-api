<?php

namespace App\Traits;

use App\Models\User;
use Google\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * App\Traits\Notification
 *
 * @property string $language
 */
trait Notification
{
    public function sendNotification(
        array $receivers = [],
        ?string $message = '',
        ?string $title = null,
        mixed $data = [],
        array $userIds = [],
        ?string $firebaseTitle = '',
    ): void {
        if (empty($receivers)) {
            return;
        }

        $url   = "https://fcm.googleapis.com/v1/projects/{$this->projectId()}/messages:send";
        $token = $this->updateToken();

        $headers = [
            'Authorization' => "Bearer $token",
            'Content-Type'  => 'application/json',
        ];

        foreach ($receivers as $receiver) {
            try {
                dispatch(function () use ($receiver, $message, $title, $data, $firebaseTitle, $headers, $url) {

                    if (empty($receiver)) {
                        return;
                    }

                    $response = Http::withHeaders($headers)->post($url, [
                        'message' => [
                            'token'        => $receiver,
                            'notification' => [
                                'title' => $firebaseTitle ?: $title,
                                'body'  => $message,
                            ],
                            'data'    => [
                                'id'     => (string) ($data['id'] ?? ''),
                                'status' => (string) ($data['status'] ?? ''),
                                'type'   => (string) ($data['type'] ?? ''),
                            ],
                            'android' => [
                                'notification' => ['sound' => 'default'],
                            ],
                            'apns' => [
                                'payload' => ['aps' => ['sound' => 'default']],
                            ],
                        ],
                    ]);

                    if (! $response->successful()) {
                        Log::error('FCM push failed', [
                            'status'   => $response->status(),
                            'body'     => $response->body(),
                            'receiver' => $receiver,
                        ]);
                    }

                })->afterResponse();

            } catch (Throwable $e) {
                Log::error('FCM dispatch error: '.$e->getMessage());
            }
        }
    }

    public function sendAllNotification(?string $title = null, mixed $data = [], ?string $firebaseTitle = ''): void
    {
        dispatch(function () use ($title, $data, $firebaseTitle) {

            User::select(['id', 'deleted_at', 'is_active', 'email_verified_at', 'phone_verified_at', 'firebase_token'])
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNotNull('email_verified_at')->orWhereNotNull('phone_verified_at'))
                ->whereNotNull('firebase_token')
                ->orderBy('id')
                ->chunk(100, function ($users) use ($title, $data, $firebaseTitle) {

                    $firebaseTokens = $users->pluck('firebase_token', 'id')->toArray();
                    $receives       = array_values(array_filter($firebaseTokens));

                    if (empty($receives)) {
                        return;
                    }

                    $this->sendNotification(
                        $receives,
                        $title,
                        $title,
                        $data,
                        array_keys($firebaseTokens),
                        $firebaseTitle,
                    );
                });

        })->afterResponse();
    }

    private function updateToken(): string
    {
        return Cache::remember('firebase_auth_token', 300, function () {
            $googleClient = new Client;
            $googleClient->setAuthConfig(storage_path('app/firebase-service-account.json'));
            $googleClient->addScope('https://www.googleapis.com/auth/firebase.messaging');

            return $googleClient->fetchAccessTokenWithAssertion()['access_token'];
        });
    }

    private function projectId(): ?string
    {
        return config('services.firebase.project_id')
            ?? \App\Models\Setting::where('key', 'firebase_project_id')->value('value');
    }
}
