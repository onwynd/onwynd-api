<?php

namespace App\Http\Controllers\API\V1\Marketing;

use App\Http\Controllers\API\BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MarketingBroadcastController extends BaseController
{
    protected function collectRecipients(array $audience): array
    {
        $emails = [];
        if (in_array('staff', $audience, true)) {
            $staff = User::whereHas('role', function ($q) {
                $q->whereNotIn('slug', ['patient', 'customer', 'therapist']);
            })->pluck('email')->all();
            $emails = array_merge($emails, $staff);
        }
        if (in_array('therapists', $audience, true)) {
            $therapists = User::whereHas('role', function ($q) {
                $q->where('slug', 'therapist');
            })->pluck('email')->all();
            $emails = array_merge($emails, $therapists);
        }
        if (in_array('customers', $audience, true)) {
            $customers = User::whereHas('role', function ($q) {
                $q->whereIn('slug', ['patient', 'customer']);
            })->pluck('email')->all();
            $emails = array_merge($emails, $customers);
        }
        if (in_array('investors', $audience, true)) {
            $investors = User::whereHas('role', function ($q) {
                $q->where('slug', 'investor');
            })->pluck('email')->all();
            $emails = array_merge($emails, $investors);
        }
        $emails = array_values(array_unique(array_filter($emails)));

        return $emails;
    }

    public function preview(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('admin')) {
            return $this->sendError('Forbidden', [], 403);
        }
        $data = $request->validate([
            'audience' => 'required|array',
        ]);
        $audience = $data['audience'];
        $emails = $this->collectRecipients($audience);

        return $this->sendResponse([
            'count' => count($emails),
        ], 'Recipient preview generated.');
    }

    public function send(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('admin')) {
            return $this->sendError('Forbidden', [], 403);
        }
        $data = $request->validate([
            'subject' => 'required|string|max:191',
            'html' => 'required|string',
            'audience' => 'required|array',
            'event_id' => 'nullable|integer',
        ]);
        $recipients = $this->collectRecipients($data['audience']);
        foreach ($recipients as $email) {
            try {
                Mail::send([], [], function ($message) use ($email, $data) {
                    $message->to($email)->subject($data['subject'])->setBody($data['html'], 'text/html');
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->sendResponse([
            'sent' => count($recipients),
        ], 'Broadcast sent.');
    }
}
