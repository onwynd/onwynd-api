<?php

namespace App\Http\Controllers\API\V1\Sales;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class OutreachController extends BaseController
{
    /**
     * Send a one-off outreach email to a lead.
     * Sends from the authenticated sales rep's name using the system mail config.
     */
    public function sendEmail(Request $request)
    {
        $data = $request->validate([
            'to'      => 'required|email',
            'subject' => 'required|string|max:255',
            'body'    => 'nullable|string',
        ]);

        $sender = $request->user();
        $senderName = trim("{$sender->first_name} {$sender->last_name}") ?: config('app.name');

        Mail::raw($data['body'] ?? '', function ($message) use ($data, $senderName) {
            $message->to($data['to'])
                    ->subject($data['subject'])
                    ->replyTo(config('mail.from.address'), $senderName);
        });

        return $this->sendResponse(['sent' => true], 'Email sent successfully.');
    }
}
