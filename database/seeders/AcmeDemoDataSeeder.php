<?php

namespace Database\Seeders;

use App\Models\Institutional\Organization;
use App\Models\Institutional\OrganizationMember;
use App\Models\JournalEntry;
use App\Models\MoodLog;
use App\Models\OnwyndScoreLog;
use App\Models\SleepLog;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * AcmeDemoDataSeeder
 *
 * Seeds realistic 8-week utilisation data for Acme Nigeria Ltd (demo org):
 *  - Mood logs    — weekly check-ins with realistic score distribution
 *  - Sleep logs   — bi-weekly sleep quality records
 *  - Journal entries — reflective notes for active employees
 *  - Onwynd score logs — weekly wellness scores showing improvement trend
 *  - Therapy session bookings — completed sessions per engagement tier
 *
 * Requires CorporateDemoSeeder to have run first.
 *
 * Run: php artisan db:seed --class=AcmeDemoDataSeeder
 */
class AcmeDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('contact_email', 'hr@acmenigeria.com')->first();

        if (! $org) {
            $this->command->error('Acme Nigeria Ltd not found. Run CorporateDemoSeeder first.');
            return;
        }

        $members = OrganizationMember::where('organization_id', $org->id)
            ->where('role', 'member')
            ->with('user')
            ->get();

        if ($members->isEmpty()) {
            $this->command->error('No employee members found for Acme Nigeria Ltd.');
            return;
        }

        $weeksBack = 8;
        $now       = Carbon::now();

        // Engagement tiers: 50% high / 30% moderate / 20% low
        $profiles = $this->buildEngagementProfiles($members->count());

        $moodCount    = 0;
        $sleepCount   = 0;
        $journalCount = 0;
        $scoreCount   = 0;
        $sessionCount = 0;

        foreach ($members as $idx => $member) {
            $user = $member->user;
            if (! $user) continue;

            $profile = $profiles[$idx];

            // ── Mood logs ────────────────────────────────────────────────────
            for ($week = $weeksBack; $week >= 0; $week--) {
                $weekStart = $now->copy()->subWeeks($week)->startOfWeek();

                for ($c = 0; $c < $profile['mood_frequency']; $c++) {
                    $loggedAt = $weekStart->copy()
                        ->addDays(rand(0, 4))
                        ->addHours(rand(8, 17));

                    if ($loggedAt->gt($now)) continue;

                    // Scores trend upward in recent weeks (Onwynd impact)
                    $base  = $profile['base_mood'];
                    $score = max(1, min(10, $base + rand(-1, 1) + ($week <= 2 ? 1 : 0)));

                    MoodLog::create([
                        'user_id'     => $user->id,
                        'mood_score'  => $score,
                        'emotions'    => $this->emotionsForScore($score),
                        'notes'       => $week <= 2 ? $this->recentNote($score) : null,
                        'activities'  => $this->randomActivities(),
                        'sleep_hours' => round(5.5 + mt_rand(0, 30) / 10, 1),
                        'created_at'  => $loggedAt,
                        'updated_at'  => $loggedAt,
                    ]);
                    $moodCount++;
                }
            }

            // ── Onwynd wellness score logs (weekly) ──────────────────────────
            for ($week = $weeksBack; $week >= 0; $week--) {
                $loggedAt = $now->copy()->subWeeks($week)->startOfWeek()
                    ->addDays(6) // End of week score
                    ->setHour(20);

                if ($loggedAt->gt($now)) continue;

                $base  = $profile['base_mood'];
                $score = max(20, min(100,
                    ($base * 8) + rand(-5, 5) + (($weeksBack - $week) * 2) // gradual improvement
                ));

                OnwyndScoreLog::create([
                    'user_id'   => $user->id,
                    'score'     => $score,
                    'breakdown' => [
                        'mood'       => round($score * 0.35),
                        'sleep'      => round($score * 0.25),
                        'activity'   => round($score * 0.20),
                        'engagement' => round($score * 0.20),
                    ],
                    'logged_at'  => $loggedAt,
                    'created_at' => $loggedAt,
                    'updated_at' => $loggedAt,
                ]);
                $scoreCount++;
            }

            // ── Sleep logs (bi-weekly) ───────────────────────────────────────
            if ($profile['sleep_logs']) {
                for ($week = $weeksBack; $week >= 0; $week -= 2) {
                    $wakeAt = $now->copy()->subWeeks($week)
                        ->addDays(rand(0, 4))
                        ->setHour(7)->setMinute(0)->setSecond(0);

                    if ($wakeAt->gt($now)) continue;

                    $durationMins = rand(360, 540); // 6–9h
                    $sleepAt      = $wakeAt->copy()->subMinutes($durationMins);

                    SleepLog::create([
                        'user_id'          => $user->id,
                        'start_time'       => $sleepAt,
                        'end_time'         => $wakeAt,
                        'duration_minutes' => $durationMins,
                        'quality_rating'   => rand(3, 5),
                        'interruptions'    => rand(0, 3),
                        'notes'            => null,
                        'source'           => 'manual',
                        'created_at'       => $wakeAt,
                        'updated_at'       => $wakeAt,
                    ]);
                    $sleepCount++;
                }
            }

            // ── Journal entries (active users only) ──────────────────────────
            if ($profile['journaling']) {
                $entryCount = rand(3, 6);
                for ($e = 0; $e < $entryCount; $e++) {
                    $loggedAt = $now->copy()
                        ->subDays(rand(0, 55))
                        ->setHour(rand(19, 22));

                    if ($loggedAt->gt($now)) continue;

                    JournalEntry::create([
                        'user_id'    => $user->id,
                        'title'      => $this->journalTitle($e),
                        'content'    => $this->journalContent(),
                        'type'       => 'text',
                        'mood_emoji' => $this->moodEmoji($profile['base_mood']),
                        'stress_level' => max(1, min(5, 5 - (int) round($profile['base_mood'] / 2))),
                        'emotions'   => $this->emotionsForScore($profile['base_mood']),
                        'is_private' => true,
                        'created_at' => $loggedAt,
                        'updated_at' => $loggedAt,
                    ]);
                    $journalCount++;
                }
            }

            // ── Therapy session bookings ─────────────────────────────────────
            for ($s = 0; $s < $profile['sessions']; $s++) {
                $sessionAt = $now->copy()
                    ->subWeeks(rand(0, $weeksBack - 1))
                    ->subDays(rand(0, 4))
                    ->setHour(rand(9, 16))
                    ->setMinute(0)->setSecond(0);

                if ($sessionAt->gt($now)) continue;

                DB::table('bookings')->insertOrIgnore([
                    'user_id'           => $user->id,
                    'booking_reference' => 'ACME-' . strtoupper(substr(md5($user->id . $s . 'x'), 0, 8)),
                    'scheduled_at'      => $sessionAt,
                    'status'            => 'completed',
                    'notes'             => 'Demo data — therapy session',
                    'total_price'       => 0,
                    'created_at'        => $sessionAt,
                    'updated_at'        => $sessionAt,
                ]);
                $sessionCount++;
            }
        }

        // Update org aggregate counts
        $org->update([
            'onboarded_count' => $members->count(),
            'current_seats'   => $members->count(),
        ]);

        $this->command->info("AcmeDemoDataSeeder complete — Acme Nigeria Ltd ({$members->count()} employees):");
        $this->command->info("  Mood logs:          {$moodCount}");
        $this->command->info("  Wellness scores:    {$scoreCount}");
        $this->command->info("  Sleep logs:         {$sleepCount}");
        $this->command->info("  Journal entries:    {$journalCount}");
        $this->command->info("  Therapy sessions:   {$sessionCount}");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildEngagementProfiles(int $count): array
    {
        $profiles = [];
        for ($i = 0; $i < $count; $i++) {
            $pct = $i / $count;

            if ($pct < 0.5) {
                // 50 % highly engaged
                $profiles[] = [
                    'mood_frequency' => 3,
                    'base_mood'      => rand(7, 9),
                    'sessions'       => rand(4, 7),
                    'sleep_logs'     => true,
                    'journaling'     => true,
                ];
            } elseif ($pct < 0.8) {
                // 30 % moderately engaged
                $profiles[] = [
                    'mood_frequency' => 2,
                    'base_mood'      => rand(5, 7),
                    'sessions'       => rand(2, 4),
                    'sleep_logs'     => true,
                    'journaling'     => false,
                ];
            } else {
                // 20 % low engagement (realistic churn risk)
                $profiles[] = [
                    'mood_frequency' => 1,
                    'base_mood'      => rand(3, 6),
                    'sessions'       => rand(0, 2),
                    'sleep_logs'     => false,
                    'journaling'     => false,
                ];
            }
        }
        return $profiles;
    }

    private function emotionsForScore(int $score): array
    {
        $positive = ['happy', 'energised', 'grateful', 'calm', 'focused', 'motivated'];
        $neutral  = ['tired', 'neutral', 'okay', 'reflective'];
        $negative = ['anxious', 'stressed', 'overwhelmed', 'sad', 'irritable'];

        $pool = $score >= 7 ? $positive : ($score >= 4 ? $neutral : $negative);
        shuffle($pool);
        return array_slice($pool, 0, rand(1, 3));
    }

    private function randomActivities(): array
    {
        $pool = ['work', 'exercise', 'meditation', 'reading', 'socialising', 'cooking', 'resting'];
        shuffle($pool);
        return array_slice($pool, 0, rand(1, 3));
    }

    private function recentNote(int $score): string
    {
        $notes = $score >= 7 ? [
            'Feeling much better since starting therapy sessions.',
            'The mindfulness exercises really helped this week.',
            'Team morale has been great lately.',
        ] : [
            'Busy week but managing with the tools available.',
            'Work pressure is high but I\'m coping better than before.',
            'Looking forward to my next therapy session.',
        ];
        return $notes[array_rand($notes)];
    }

    private function moodEmoji(int $score): string
    {
        if ($score >= 8) return '😊';
        if ($score >= 6) return '🙂';
        if ($score >= 4) return '😐';
        return '😔';
    }

    private function journalTitle(int $idx): string
    {
        $titles = [
            'Reflecting on this week',
            'Small wins worth celebrating',
            'Processing a tough day',
            'Gratitude check-in',
            'Setting intentions',
            'End-of-week thoughts',
        ];
        return $titles[$idx % count($titles)];
    }

    private function journalContent(): string
    {
        $entries = [
            'Today was challenging but I managed to get through it. I noticed I was more patient with myself.',
            'Feeling grateful for the support I have. The sessions have been really helpful.',
            'Had a tough meeting but I took some time to breathe and reset. Progress.',
            'Noticed I slept better this week. The sleep tracking reminder actually works for me.',
            'Tried the 5-minute meditation before the all-hands. Much calmer going in.',
            'Checked my Onwynd score — went up 8 points this week. Motivation to keep going.',
        ];
        return $entries[array_rand($entries)];
    }
}
