<?php

// Script to give test users active subscriptions for testing
require_once 'vendor/autoload.php';

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;

$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Giving test users active subscriptions...\n\n";

// Get the first available subscription plan
$plan = SubscriptionPlan::first();
if (! $plan) {
    echo "Error: No subscription plans found. Please run seeders first.\n";
    exit(1);
}

echo "Using subscription plan: {$plan->name}\n";

// Update patient user
$patient = User::where('email', 'patient@onwynd.com')->first();
if ($patient) {
    // Check if patient already has an active subscription
    $existingSubscription = $patient->subscriptions()->where('status', 'active')->first();

    if (! $existingSubscription) {
        // Create new active subscription
        $subscription = new Subscription([
            'user_id' => $patient->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonths(1),
            'auto_renew' => true,
        ]);
        $subscription->save();

        echo '✓ Patient user given active subscription (expires: '.$subscription->current_period_end.")\n";
    } else {
        echo '✓ Patient user already has active subscription (expires: '.$existingSubscription->current_period_end.")\n";
    }
}

// Update admin user
$admin = User::where('email', 'admin@onwynd.com')->first();
if ($admin) {
    // Check if admin already has an active subscription
    $existingSubscription = $admin->subscriptions()->where('status', 'active')->first();

    if (! $existingSubscription) {
        // Create new active subscription
        $subscription = new Subscription([
            'user_id' => $admin->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonths(1),
            'auto_renew' => true,
        ]);
        $subscription->save();

        echo '✓ Admin user given active subscription (expires: '.$subscription->current_period_end.")\n";
    } else {
        echo '✓ Admin user already has active subscription (expires: '.$existingSubscription->current_period_end.")\n";
    }
}

echo "\nSubscription updates completed!\n";
