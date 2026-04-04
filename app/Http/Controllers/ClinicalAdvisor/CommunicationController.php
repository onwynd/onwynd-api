<?php

namespace App\Http\Controllers\ClinicalAdvisor;

use App\Http\Controllers\Controller;
use App\Mail\MeetingInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CommunicationController extends Controller
{
    public function sendMeetingInvite(Request $request, string $id)
    {
        $request->validate([
            'title' => 'required|string|max:191',
            'date' => 'required|date',
            'time' => 'required|string|max:20',
            'location' => 'required|string|max:191',
            'agenda' => 'nullable|string|max:2000',
        ]);

        $therapist = User::whereHas('role', function ($q) {
            $q->where('slug', 'therapist');
        })->findOrFail($id);

        $advisor = $request->user();

        $token = Str::random(32);
        $acceptLink = url('/dashboard/meetings/respond?status=accepted&token='.$token);
        $declineLink = url('/dashboard/meetings/respond?status=declined&token='.$token);

        Mail::to($therapist->email)->queue(new MeetingInvitation(
            $advisor->email,
            $request->string('title'),
            $request->string('date'),
            $request->string('time'),
            $request->string('location'),
            (string) $request->input('agenda', ''),
            $acceptLink,
            $declineLink
        ));

        return response()->json([
            'message' => 'Meeting invitation sent to therapist.',
        ]);
    }
}
