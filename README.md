# Onwynd API

Backend API for the Onwynd mental health platform — connecting patients with therapists, delivering guided wellness content, and powering organizational mental health programmes.

Built with **Laravel 12** · **PHP 8.2+** · **MySQL** · **Redis** · **Laravel Sanctum**

---

## Features

- **Auth** — registration, login, token refresh, cookie-based auth, social login scaffold
- **Therapist marketplace** — profiles, availability scheduling, session booking, ratings, introductory pricing, location verification
- **Therapy sessions** — individual & group sessions, LiveKit video integration, session notes
- **Patient management** — assessments (PHQ-9, GAD-7, PSS-10, WHO-5), mood tracking, habit logging
- **Wellness content** — guided meditations, sleep sounds, mindfulness audio, editorial articles
- **Gamification** — badges, streaks, points
- **Organisations** — multi-tenant corporate wellness, quota management, pilot contracts, budget approval workflow
- **AI chat** — quota-gated AI therapy assistant (Groq / Perplexity)
- **Payments** — Stripe Connect for therapist payouts, subscription billing
- **Real-time** — Laravel Reverb (WebSockets) + Pusher
- **Notifications** — Firebase push notifications, email
- **Admin** — KPI snapshots, platform branding, user management, corporate contracts

---

## Requirements

| Dependency | Version |
|------------|---------|
| PHP | ^8.2 |
| MySQL | 8.0+ |
| Redis | 6+ |
| Composer | 2.x |
| Node.js | 18+ |

---

## Getting Started

```bash
# 1. Clone
git clone https://github.com/onwynd/onwynd-api.git
cd onwynd-api

# 2. Install PHP dependencies
composer install

# 3. Environment
cp .env.example .env
php artisan key:generate

# 4. Configure .env
#    Set DB_*, REDIS_*, STRIPE_*, FIREBASE_*, LIVEKIT_* values

# 5. Migrate & seed
php artisan migrate
php artisan db:seed

# 6. Storage link
php artisan storage:link

# 7. Run
php artisan serve
```

Or use the composer shortcut:

```bash
composer setup   # install + .env + key:generate + migrate
composer dev     # server + queue + logs + vite (concurrent)
```

---

## Key Environment Variables

```env
APP_URL=https://api.onwynd.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=onwynd

REDIS_HOST=127.0.0.1

SANCTUM_STATEFUL_DOMAINS=onwynd.com

STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=

LIVEKIT_URL=
LIVEKIT_API_KEY=
LIVEKIT_API_SECRET=

FIREBASE_CREDENTIALS=storage/app/firebase-service-account.json

GROQ_API_KEY=
```

See `.env.example` for the full list.

---

## API Documentation

Swagger UI is available at `/api/documentation` when `APP_ENV=local`.

To regenerate docs:

```bash
php artisan l5-swagger:generate
```

---

## Testing

```bash
composer test
# or
php artisan test
```

---

## Deployment

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan storage:link
```

Queue worker (Supervisor recommended):

```bash
php artisan queue:work --tries=3 --timeout=90
```

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 12 |
| Auth | Laravel Sanctum |
| Real-time | Laravel Reverb + Pusher |
| Search | Laravel Scout |
| Payments | Stripe |
| Video | LiveKit |
| Push notifications | Firebase FCM |
| Storage | AWS S3 / local |
| Cache & queues | Redis |
| Monitoring | Sentry |
| API docs | L5-Swagger (OpenAPI 3) |
