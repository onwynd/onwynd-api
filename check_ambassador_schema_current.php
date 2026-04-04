<?php

// Check current ambassador table schema
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Schema;

$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Current ambassador table schema:\n\n";

if (Schema::hasTable('ambassadors')) {
    $columns = Schema::getColumnListing('ambassadors');
    foreach ($columns as $column) {
        echo "- $column: ".Schema::getColumnType('ambassadors', $column)."\n";
    }
} else {
    echo "Ambassadors table does not exist!\n";
}

echo "\nChecking if migration has been run...\n";
$migrations = \DB::table('migrations')->where('migration', 'like', '%ambassador%')->get();
foreach ($migrations as $migration) {
    echo "- {$migration->migration} (batch {$migration->batch})\n";
}
