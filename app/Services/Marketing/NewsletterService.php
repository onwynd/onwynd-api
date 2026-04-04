<?php

namespace App\Services\Marketing;

use App\Models\Lead;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NewsletterService
{
    public function subscribe(string $email): NewsletterSubscriber
    {
        $normalizedEmail = strtolower(trim($email));

        $subscriber = NewsletterSubscriber::firstOrCreate(
            ['email' => $normalizedEmail],
            [
                'status' => 'pending',
                'confirmation_token' => Str::random(40),
                'unsubscribe_token' => Str::random(40),
            ]
        );

        $lead = Lead::where('email', $normalizedEmail)->first();
        if ($lead) {
            $lead->update([
                'status' => $lead->status ?: 'new',
                'source' => $lead->source ?: 'newsletter',
            ]);
        } else {
            Lead::create([
                'first_name' => '',
                'last_name' => '',
                'email' => $normalizedEmail,
                'phone' => null,
                'company' => null,
                'status' => 'new',
                'source' => 'newsletter',
                'notes' => now()->format('Y-m-d H:i')."\nsource: newsletter",
            ]);
        }

        if ($subscriber->status === 'confirmed') {
            return $subscriber;
        }

        if (! $subscriber->confirmation_token) {
            $subscriber->confirmation_token = Str::random(40);
            if (! $subscriber->unsubscribe_token) {
                $subscriber->unsubscribe_token = Str::random(40);
            }
            $subscriber->save();
        }

        $this->sendConfirmationEmail($subscriber);

        return $subscriber;
    }

    public function confirm(string $token): ?NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::where('confirmation_token', $token)->first();
        if (! $subscriber) {
            return null;
        }
        $subscriber->status = 'confirmed';
        $subscriber->confirmed_at = now();
        $subscriber->confirmation_token = null;
        $subscriber->save();
        $this->sendWelcomeEmail($subscriber);

        return $subscriber;
    }

    public function unsubscribeByToken(string $token): ?NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $token)->first();
        if (! $subscriber) {
            return null;
        }
        $subscriber->status = 'unsubscribed';
        $subscriber->unsubscribed_at = now();
        $subscriber->save();

        return $subscriber;
    }

    public function unsubscribeByEmail(string $email): ?NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::where('email', strtolower($email))->first();
        if (! $subscriber) {
            return null;
        }
        $subscriber->status = 'unsubscribed';
        $subscriber->unsubscribed_at = now();
        $subscriber->save();

        return $subscriber;
    }

    public function list(array $filters = [], int $perPage = 20)
    {
        $q = NewsletterSubscriber::query();
        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        $q->orderByDesc('created_at');

        return $q->paginate($perPage);
    }

    protected function sendConfirmationEmail(NewsletterSubscriber $subscriber): void
    {
        $base = config('app.url', 'https://onwynd.com');
        $confirmUrl = rtrim($base, '/').'/api/v1/marketing/newsletter/confirm/'.$subscriber->confirmation_token;
        $frontend = env('FRONTEND_URL') ?: $base;
        $unsubscribeUrl = rtrim($frontend, '/').'/unsubscribe/'.$subscriber->unsubscribe_token;
        $html = '<div style="font-family:Arial,sans-serif">
            <h2>Confirm your subscription</h2>
            <p>Please confirm your email to start receiving Onwynd updates.</p>
            <p><a href="'.$confirmUrl.'" style="display:inline-block;padding:10px 16px;background:#2e7d32;color:#fff;text-decoration:none;border-radius:6px">Confirm Subscription</a></p>
            <p>If the button doesn\'t work, open this link: '.$confirmUrl.'</p>
            <hr style="border:none;border-top:1px solid #eee;margin:20px 0" />
            <p style="font-size:12px;color:#666">If you didn\'t request this or prefer not to receive updates, you can <a href="'.$unsubscribeUrl.'">unsubscribe here</a>.</p>
        </div>';
        try {
            Mail::send([], [], function ($message) use ($subscriber, $html) {
                $message->to($subscriber->email)->subject('Confirm your Onwynd newsletter')->html($html);
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function sendWelcomeEmail(NewsletterSubscriber $subscriber): void
    {
        $base = config('app.url', 'https://onwynd.com');
        $frontend = env('FRONTEND_URL') ?: $base;
        $unsubscribeUrl = rtrim($frontend, '/').'/unsubscribe/'.$subscriber->unsubscribe_token;
        $html = '<div style="font-family:Arial,sans-serif">
            <h2>Welcome to Onwynd</h2>
            <p>You\'re subscribed. We\'ll send occasional updates on wellness, AI, and product news.</p>
            <p>Visit <a href="https://onwynd.com">onwynd.com</a></p>
            <hr style="border:none;border-top:1px solid #eee;margin:20px 0" />
            <p style="font-size:12px;color:#666">To stop receiving these emails, <a href="'.$unsubscribeUrl.'">unsubscribe here</a>.</p>
        </div>';
        try {
            Mail::send([], [], function ($message) use ($subscriber, $html) {
                $message->to($subscriber->email)->subject('Welcome to Onwynd')->html($html);
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
