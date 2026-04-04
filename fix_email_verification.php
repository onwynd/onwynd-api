<?php

// Script to fix email verification for test users
require_once 'vendor/autoload.php';

use App\Models\User;

$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Fixing email verification for test users...\n\n";

// Update patient user
$patient = User::where('email', 'patient@onwynd.com')->first();
if ($patient) {
    $patient->email_verified_at = now();
    $patient->save();
    echo "✓ Patient user email verified\n";
}

// Update admin user
$admin = User::where('email', 'admin@onwynd.com')->first();
if ($admin) {
    $admin->email_verified_at = now();
    $admin->save();
    echo "✓ Admin user email verified\n";
}

echo "\nEmail verification completed!\n";
