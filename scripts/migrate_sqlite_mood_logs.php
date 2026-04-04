<?php

/**
 * Standalone SQLite migration for mood_logs table.
 * Uses PDO to create the table and indexes when artisan is unavailable.
 */

declare(strict_types=1);

function dbPath(): string
{
    $path = __DIR__.'/../database/database.sqlite';
    if (! file_exists($path)) {
        // Attempt to create empty database file
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        touch($path);
    }

    return realpath($path) ?: $path;
}

function connect(string $dbFile): PDO
{
    $dsn = 'sqlite:'.$dbFile;
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Enable foreign keys
    $pdo->exec('PRAGMA foreign_keys = ON;');

    return $pdo;
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
    $stmt->execute([$table]);

    return (bool) $stmt->fetchColumn();
}

function createMoodLogs(PDO $pdo): void
{
    // Use TEXT for JSON columns in SQLite
    // Use INTEGER for tiny integer mood_score
    // Use NUMERIC for sleep_hours
    $sql = <<<'SQL'
CREATE TABLE mood_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  patient_id INTEGER NULL,
  mood_score INTEGER NULL,
  emotions TEXT NULL,
  notes TEXT NULL,
  activities TEXT NULL,
  sleep_hours NUMERIC NULL,
  weather_data TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  CONSTRAINT fk_mood_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_mood_logs_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
);
SQL;

    $pdo->exec($sql);

    // Indexes to match migration intent
    $pdo->exec('CREATE INDEX idx_mood_logs_user_id ON mood_logs(user_id);');
    $pdo->exec('CREATE INDEX idx_mood_logs_patient_id ON mood_logs(patient_id);');
}

function main(): void
{
    $dbFile = dbPath();
    echo "Using SQLite database: {$dbFile}\n";
    $pdo = connect($dbFile);

    if (tableExists($pdo, 'mood_logs')) {
        echo "Table 'mood_logs' already exists. Nothing to do.\n";

        return;
    }

    createMoodLogs($pdo);
    echo "Table 'mood_logs' created successfully.\n";
}

main();
