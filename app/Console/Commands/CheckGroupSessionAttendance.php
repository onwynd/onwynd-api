<?php

namespace App\Console\Commands;

use App\Models\GroupSession;
use App\Notifications\GroupSessionNotification;
use Illuminate\Console\Command;

class CheckGroupSessionAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-group-session-attendance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check group sessions 15m before start and alert therapist if attendance is low';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Find sessions starting in 15-20 minutes
        $sessions = GroupSession::where('scheduled_at', '<=', now()->addMinutes(20))
            ->where('scheduled_at', '>', now()->addMinutes(14))
            ->where('status', 'scheduled')
            ->get();

        foreach ($sessions as $session) {
            $count = $session->participants()->where('invite_status', 'accepted')->count();

            if ($count < 2) {
                // Notify therapist
                if ($session->therapist) {
                    $session->therapist->notify(new GroupSessionNotification($session, 'low_attendance', ['count' => $count]));
                }

                // Notify organiser (if different from therapist)
                if ($session->organiser_id && $session->organiser_id !== $session->therapist_id) {
                    $organiser = \App\Models\User::find($session->organiser_id);
                    if ($organiser) {
                        $organiser->notify(new GroupSessionNotification($session, 'low_attendance', ['count' => $count]));
                    }
                }

                $this->warn('Low attendance alert sent for session: '.$session->title.' (Count: '.$count.')');
            }
        }

        return 0;
    }
}
