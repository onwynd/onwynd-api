<?php

namespace App\Http\Controllers\API\V1\Marketing;

use App\Http\Controllers\API\BaseController;
use App\Services\Marketing\NewsletterService;
use Illuminate\Http\Request;

class NewsletterController extends BaseController
{
    public function __construct(protected NewsletterService $service) {}

    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('admin')) {
            return $this->sendError('Forbidden', [], 403);
        }
        $status = $request->query('status');
        $perPage = (int) ($request->query('per_page', 20));
        $data = $this->service->list(['status' => $status], $perPage);

        return $this->sendResponse($data, 'Subscribers retrieved.');
    }

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:191',
        ]);
        $subscriber = $this->service->subscribe($data['email']);

        return $this->sendResponse([
            'status' => $subscriber->status,
            'email' => $subscriber->email,
        ], 'If your email is valid, a confirmation message has been sent.');
    }

    public function confirm(string $token)
    {
        $subscriber = $this->service->confirm($token);
        if (! $subscriber) {
            return $this->sendError('Invalid confirmation token', [], 404);
        }

        return $this->sendResponse([
            'status' => $subscriber->status,
            'email' => $subscriber->email,
        ], 'Subscription confirmed.');
    }

    public function unsubscribeToken(string $token)
    {
        $subscriber = $this->service->unsubscribeByToken($token);
        if (! $subscriber) {
            return $this->sendError('Invalid unsubscribe token', [], 404);
        }

        return $this->sendResponse([
            'status' => $subscriber->status,
            'email' => $subscriber->email,
        ], 'You have been unsubscribed.');
    }

    public function unsubscribeEmail(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:191',
        ]);
        $subscriber = $this->service->unsubscribeByEmail($data['email']);
        if (! $subscriber) {
            return $this->sendError('Subscriber not found', [], 404);
        }

        return $this->sendResponse([
            'status' => $subscriber->status,
            'email' => $subscriber->email,
        ], 'You have been unsubscribed.');
    }
}
