<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Artisan command to verify that audio/media files referenced in the database
 * actually exist in storage. Reports missing files and optionally nullifies
 * broken references so the app no longer serves dead URLs.
 *
 * Usage:
 *   php artisan onwynd:verify-audio              # dry-run
 *   php artisan onwynd:verify-audio --fix        # nullify broken refs in DB
 *   php artisan onwynd:verify-audio --disk=s3    # check against S3 disk
 */
class VerifyAudioFilesCommand extends Command
{
    protected $signature = 'onwynd:verify-audio
                            {--fix  : Nullify broken file references in the database}
                            {--disk=public : Storage disk to check against}';

    protected $description = 'Verify audio/media files in DB exist in storage; report (and optionally fix) missing files';

    /** Tables and their audio columns to verify */
    private array $targets = [
        ['table' => 'resources',            'col' => 'media_url',       'id' => 'id'],
        ['table' => 'resources',            'col' => 'preview_url',     'id' => 'id'],
        ['table' => 'mindfulness_sessions', 'col' => 'audio_file_path', 'id' => 'id'],
        ['table' => 'soundscapes',          'col' => 'audio_file_path', 'id' => 'id'],
        ['table' => 'soundscapes',          'col' => 'preview_url',     'id' => 'id'],
    ];

    private int $checked = 0;

    private int $missing = 0;

    private int $fixed = 0;

    public function handle(): int
    {
        $disk = $this->option('disk');
        $doFix = (bool) $this->option('fix');
        $storage = Storage::disk($disk);

        $this->info("Checking audio files on disk: [{$disk}]".($doFix ? ' (FIX mode)' : ' (dry-run)'));
        $this->newLine();

        foreach ($this->targets as $target) {
            $this->checkTable($target, $storage, $doFix);
        }

        $this->newLine();
        $this->info("Summary: checked={$this->checked}  missing={$this->missing}  fixed={$this->fixed}");

        return $this->missing > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function checkTable(array $target, \Illuminate\Contracts\Filesystem\Filesystem $storage, bool $doFix): void
    {
        $table = $target['table'];
        $col = $target['col'];
        $idCol = $target['id'];

        // Skip tables that don't exist (migrations not run yet, different envs)
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            $this->line("  <comment>SKIP</comment>  {$table}.{$col} — table not found");

            return;
        }
        if (! DB::getSchemaBuilder()->hasColumn($table, $col)) {
            $this->line("  <comment>SKIP</comment>  {$table}.{$col} — column not found");

            return;
        }

        $rows = DB::table($table)
            ->whereNotNull($col)
            ->where($col, '!=', '')
            ->select([$idCol, $col])
            ->get();

        $tableTotal = 0;
        $tableMissing = 0;

        foreach ($rows as $row) {
            $path = $row->{$col};

            // Strip storage/ prefix and full URL prefix if stored as URL
            $relativePath = $this->normalise($path);

            $exists = $storage->exists($relativePath);
            $tableTotal++;
            $this->checked++;

            if (! $exists) {
                $tableMissing++;
                $this->missing++;
                $this->warn("  MISSING  {$table}#{$row->{$idCol}}.{$col} → {$relativePath}");

                if ($doFix) {
                    DB::table($table)->where($idCol, $row->{$idCol})->update([$col => null]);
                    $this->fixed++;
                }
            }
        }

        $status = $tableMissing === 0 ? '<info>OK</info>' : '<error>ERRORS</error>';
        $this->line("  {$status}  {$table}.{$col} — {$tableTotal} checked, {$tableMissing} missing");
    }

    /**
     * Normalise a stored value (may be a full URL or relative path) to a
     * disk-relative path.
     */
    private function normalise(string $raw): string
    {
        // Strip full URL (http://... or https://...) leaving the path portion
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            $parsed = parse_url($raw);
            $raw = $parsed['path'] ?? $raw;
        }

        // Remove leading /storage/ or storage/ prefix added by Laravel
        $raw = ltrim($raw, '/');
        if (str_starts_with($raw, 'storage/')) {
            $raw = substr($raw, 8);
        }

        return $raw;
    }
}
