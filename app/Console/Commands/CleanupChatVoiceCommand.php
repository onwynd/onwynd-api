<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupChatVoiceCommand extends Command
{
    protected $signature = 'media:cleanup-chat-voice';

    protected $description = 'Delete chat voice files older than retention period';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $dir = 'chat-voice';
        $retention = (int) config('services.transcriber.retention_hours', 24);
        $threshold = now()->subHours($retention)->getTimestamp();

        $deleted = 0;
        foreach ($disk->files($dir) as $file) {
            $modified = $disk->lastModified($file);
            if ($modified < $threshold) {
                if ($disk->delete($file)) {
                    $deleted++;
                }
            }
        }

        $this->info('Deleted files: '.$deleted);

        return self::SUCCESS;
    }
}
