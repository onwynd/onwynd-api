<?php

namespace Database\Seeders;

use App\Models\MarketingEvent;
use Illuminate\Database\Seeder;

class MarketingEventSeeder extends Seeder
{
    public function run(): void
    {
        $startYear = (int) date('Y');
        $endYear = 2036;

        for ($year = $startYear; $year <= $endYear; $year++) {
            $events = $this->generateYearEvents($year);
            foreach ($events as $event) {
                MarketingEvent::updateOrCreate(
                    [
                        'name' => $event['name'],
                        'event_date' => $event['event_date'],
                    ],
                    [
                        'audience' => $event['audience'] ?? null,
                        'description' => $event['description'] ?? null,
                        'template_html' => $this->buildTemplate($event['name'], $event['description'] ?? ''),
                        'active' => true,
                    ]
                );
            }
        }
    }

    protected function generateYearEvents(int $year): array
    {
        $events = [];

        // Helpers
        $motherDay = $this->secondSundayOfMay($year);
        $easter = $this->easterSunday($year);

        // Global/fixed-date events
        $events[] = [
            'name' => 'World Health Day',
            'event_date' => "$year-04-07",
            'audience' => ['customers', 'therapists', 'staff'],
            'description' => 'Raising awareness of global health and mental wellbeing.',
        ];
        $events[] = [
            'name' => 'World Mental Health Day',
            'event_date' => "$year-10-10",
            'audience' => ['customers', 'therapists', 'staff', 'investors'],
            'description' => 'Promoting mental health awareness and supportive care for all.',
        ];
        $events[] = [
            'name' => 'World Suicide Prevention Day',
            'event_date' => "$year-09-10",
            'audience' => ['customers', 'therapists', 'staff'],
            'description' => 'Spreading hope, awareness and access to support resources.',
        ];
        $events[] = [
            'name' => 'International Day of Persons with Disabilities',
            'event_date' => "$year-12-03",
            'audience' => ['customers', 'therapists', 'staff'],
            'description' => 'Advocating inclusion and equal opportunities for people with disabilities.',
        ];
        $events[] = [
            'name' => 'New Year Greetings',
            'event_date' => "$year-01-01",
            'audience' => ['customers', 'therapists', 'staff', 'investors'],
            'description' => 'Wishing our community a healthy and hopeful new year.',
        ];
        $events[] = [
            'name' => 'Valentine\'s Day',
            'event_date' => "$year-02-14",
            'audience' => ['customers'],
            'description' => 'A message of compassion, care, and connection from Onwynd.',
        ];
        $events[] = [
            'name' => 'International Women\'s Day',
            'event_date' => "$year-03-08",
            'audience' => ['customers', 'staff'],
            'description' => 'Celebrating the strength and resilience of women everywhere.',
        ];
        $events[] = [
            'name' => 'Workers\' Day',
            'event_date' => "$year-05-01",
            'audience' => ['staff', 'therapists'],
            'description' => 'Honouring dedication and wellbeing in the workplace.',
        ];
        $events[] = [
            'name' => 'Mother\'s Day',
            'event_date' => $motherDay->format('Y-m-d'),
            'audience' => ['customers'],
            'description' => 'Appreciating mothers and caregivers for their endless love.',
        ];
        $events[] = [
            'name' => 'Africa Day',
            'event_date' => "$year-05-25",
            'audience' => ['customers', 'investors', 'staff'],
            'description' => 'Celebrating unity, culture, and the future of mental health in Africa.',
        ];
        $events[] = [
            'name' => 'Easter Sunday',
            'event_date' => $easter->format('Y-m-d'),
            'audience' => ['customers'],
            'description' => 'A message of hope and renewal for Easter.',
        ];
        $events[] = [
            'name' => 'Children\'s Day (Nigeria)',
            'event_date' => "$year-05-27",
            'audience' => ['customers'],
            'description' => 'Supporting children’s mental wellbeing and brighter futures.',
        ];
        $events[] = [
            'name' => 'Christmas Greetings',
            'event_date' => "$year-12-25",
            'audience' => ['customers', 'therapists', 'staff', 'investors'],
            'description' => 'Warm festive wishes and gratitude from Onwynd.',
        ];

        // Country-specific (selected African countries)
        $events[] = [
            'name' => 'Nigeria Independence Day',
            'event_date' => "$year-10-01",
            'audience' => ['customers', 'investors', 'staff'],
            'description' => 'Celebrating Nigeria’s progress and the power of community wellbeing.',
        ];
        $events[] = [
            'name' => 'Ghana Independence Day',
            'event_date' => "$year-03-06",
            'audience' => ['customers', 'investors', 'staff'],
            'description' => 'Honouring Ghana’s independence and mental health for all.',
        ];
        $events[] = [
            'name' => 'South Africa Freedom Day',
            'event_date' => "$year-04-27",
            'audience' => ['customers', 'investors', 'staff'],
            'description' => 'Freedom, dignity, and access to compassionate care.',
        ];
        $events[] = [
            'name' => 'Kenya Jamhuri Day',
            'event_date' => "$year-12-12",
            'audience' => ['customers', 'investors', 'staff'],
            'description' => 'Celebrating Kenya’s independence and shared wellbeing.',
        ];
        $events[] = [
            'name' => 'Egypt Revolution Day',
            'event_date' => "$year-07-23",
            'audience' => ['customers', 'investors', 'staff'],
            'description' => 'Reflecting on progress and supporting mental wellness.',
        ];
        $events[] = [
            'name' => 'South Africa Heritage Day',
            'event_date' => "$year-09-24",
            'audience' => ['customers', 'staff'],
            'description' => 'Celebrating diverse cultures and shared identity.',
        ];
        $events[] = [
            'name' => 'Kenya Madaraka Day',
            'event_date' => "$year-06-01",
            'audience' => ['customers', 'staff'],
            'description' => 'Honouring self-governance and collective wellbeing.',
        ];
        $events[] = [
            'name' => 'South Africa Youth Day',
            'event_date' => "$year-06-16",
            'audience' => ['customers', 'staff'],
            'description' => 'Commemorating youth and the pursuit of a brighter future.',
        ];
        $events[] = [
            'name' => 'Ghana Founders’ Day',
            'event_date' => "$year-08-04",
            'audience' => ['customers', 'staff'],
            'description' => 'Honouring founding fathers and national progress.',
        ];
        $events[] = [
            'name' => 'Kenya Mashujaa Day',
            'event_date' => "$year-10-20",
            'audience' => ['customers', 'staff'],
            'description' => 'Celebrating national heroes and shared resilience.',
        ];
        $events[] = [
            'name' => 'Egypt Armed Forces Day',
            'event_date' => "$year-10-06",
            'audience' => ['customers', 'staff'],
            'description' => 'Honouring service and national pride.',
        ];
        $events[] = [
            'name' => 'Ethiopia Timkat',
            'event_date' => "$year-01-19",
            'audience' => ['customers'],
            'description' => 'Celebrating tradition and community.',
        ];
        $events[] = [
            'name' => 'Rwanda Liberation Day',
            'event_date' => "$year-07-04",
            'audience' => ['customers', 'staff'],
            'description' => 'Commemorating liberation and national unity.',
        ];
        $events[] = [
            'name' => 'Uganda Independence Day',
            'event_date' => "$year-10-09",
            'audience' => ['customers', 'staff'],
            'description' => 'Celebrating independence and wellbeing for all.',
        ];
        $events[] = [
            'name' => 'Tanzania Union Day',
            'event_date' => "$year-04-26",
            'audience' => ['customers', 'staff'],
            'description' => 'Celebrating unity and progress.',
        ];
        $events[] = [
            'name' => 'Côte d’Ivoire Independence Day',
            'event_date' => "$year-08-07",
            'audience' => ['customers', 'staff'],
            'description' => 'Celebrating independence and shared wellbeing.',
        ];
        $events[] = [
            'name' => 'Senegal Independence Day',
            'event_date' => "$year-04-04",
            'audience' => ['customers', 'staff'],
            'description' => 'Celebrating freedom and progress.',
        ];
        $events[] = [
            'name' => 'Morocco Throne Day',
            'event_date' => "$year-07-30",
            'audience' => ['customers', 'staff'],
            'description' => 'Honouring heritage and national identity.',
        ];
        $events[] = [
            'name' => 'Algeria Revolution Day',
            'event_date' => "$year-11-01",
            'audience' => ['customers', 'staff'],
            'description' => 'Commemorating the revolution and national spirit.',
        ];
        $events[] = [
            'name' => 'Tunisia Independence Day',
            'event_date' => "$year-03-20",
            'audience' => ['customers', 'staff'],
            'description' => 'Celebrating freedom and wellbeing.',
        ];
        $events[] = [
            'name' => 'Cameroon National Day',
            'event_date' => "$year-05-20",
            'audience' => ['customers', 'staff'],
            'description' => 'National pride and collective progress.',
        ];

        return $events;
    }

    protected function buildTemplate(string $title, string $desc): string
    {
        $site = config('app.url', 'https://onwynd.com');

        return '<div style="font-family:Arial,sans-serif;line-height:1.6">
  <div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.06);overflow:hidden">
    <div style="background:#9bb068;padding:20px 24px;color:#fff">
      <h1 style="margin:0;font-size:22px">'.htmlspecialchars($title).'</h1>
    </div>
    <div style="padding:24px">
      <p style="margin-top:0;color:#1f160f">'.htmlspecialchars($desc).'</p>
      <p style="color:#1f160f">At Onwynd, we’re building kinder technology for mental wellness. If today is meaningful to you and yours, we’re thinking of you.</p>
      <p><a href="'.$site.'" style="display:inline-block;padding:12px 18px;background:#4b3425;color:#fff;text-decoration:none;border-radius:8px">Visit Onwynd</a></p>
      <hr style="border:none;border-top:1px solid #eee;margin:24px 0" />
      <p style="font-size:12px;color:#666">You’re receiving this because you’re part of the Onwynd community. To manage preferences, visit your account settings or unsubscribe from a newsletter email.</p>
    </div>
  </div>
</div>';
    }

    protected function secondSundayOfMay(int $year): \DateTimeImmutable
    {
        $date = new \DateTimeImmutable("$year-05-01");
        // Find first Sunday
        $dayOfWeek = (int) $date->format('w'); // 0=Sun
        $offsetToSunday = (7 - $dayOfWeek) % 7;
        $firstSunday = $date->modify("+$offsetToSunday day");

        // Second Sunday
        return $firstSunday->modify('+7 days');
    }

    protected function easterSunday(int $year): \DateTimeImmutable
    {
        // Meeus/Jones/Butcher algorithm
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }
}
