<?php

namespace Database\Seeders;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NewsletterSubscriberSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['email' => 'alice@example.com', 'status' => 'pending'],
            ['email' => 'bob@example.com', 'status' => 'confirmed'],
            ['email' => 'carol@example.com', 'status' => 'confirmed'],
            ['email' => 'dave@example.com', 'status' => 'unsubscribed'],
            ['email' => 'erin@example.com', 'status' => 'pending'],
            ['email' => 'frank@example.com', 'status' => 'confirmed'],
            ['email' => 'grace@example.com', 'status' => 'confirmed'],
            ['email' => 'heidi@example.com', 'status' => 'unsubscribed'],
            ['email' => 'ivan@example.com', 'status' => 'confirmed'],
            ['email' => 'judy@example.com', 'status' => 'pending'],
        ];

        foreach ($samples as $s) {
            $sub = NewsletterSubscriber::firstOrCreate(
                ['email' => $s['email']],
                [
                    'status' => $s['status'],
                    'confirmation_token' => Str::random(40),
                    'unsubscribe_token' => Str::random(40),
                ]
            );
            if ($s['status'] === 'confirmed') {
                $sub->confirmed_at = now()->subDays(rand(1, 30));
                $sub->confirmation_token = null;
            }
            if ($s['status'] === 'unsubscribed') {
                $sub->unsubscribed_at = now()->subDays(rand(1, 30));
            }
            $sub->save();
        }
    }
}
