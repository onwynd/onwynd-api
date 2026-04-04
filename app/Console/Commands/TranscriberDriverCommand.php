<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TranscriberDriverCommand extends Command
{
    protected $signature = 'stt:driver {--show} {--set=} {--url=} {--verify-ssl=}';

    protected $description = 'Show or set the speech-to-text driver and endpoint';

    public function handle(): int
    {
        if ($this->option('show')) {
            $this->showCurrent();

            return self::SUCCESS;
        }

        $set = $this->option('set');
        $url = $this->option('url');
        $verify = $this->option('verify-ssl');

        if ($set === null && $url === null && $verify === null) {
            $this->showCurrent();
            $this->line('');
            $this->line('Usage examples:');
            $this->line('  php artisan stt:driver --set=local_whisper --url=https://stt.onwynd.com/asr?output=json');
            $this->line('  php artisan stt:driver --set=openai');
            $this->line('  php artisan stt:driver --verify-ssl=false');

            return self::SUCCESS;
        }

        $changes = [];
        if ($set !== null) {
            $changes['TRANSCRIBER_DRIVER'] = $set;
        }
        if ($url !== null) {
            $changes['LOCAL_WHISPER_URL'] = $url;
        }
        if ($verify !== null) {
            $verifyVal = strtolower($verify);
            $changes['TRANSCRIBER_VERIFY_SSL'] = in_array($verifyVal, ['1', 'true', 'yes'], true) ? 'true' : 'false';
        }

        if ($this->applyEnvChanges($changes)) {
            $this->info('Updated configuration:');
            foreach ($changes as $k => $v) {
                $this->line($k.'='.$v);
            }
            $this->line('');
            $this->line('Run: php artisan config:clear');

            return self::SUCCESS;
        }

        $this->error('Failed to update configuration');

        return self::FAILURE;
    }

    protected function showCurrent(): void
    {
        $driver = config('services.transcriber.driver', 'openai');
        $url = config('services.transcriber.local_whisper.url');
        $verify = config('services.transcriber.verify', true) ? 'true' : 'false';
        $this->info('Current STT configuration:');
        $this->line('Driver: '.$driver);
        $this->line('URL: '.($url ?: 'n/a'));
        $this->line('Verify SSL: '.$verify);
    }

    protected function applyEnvChanges(array $changes): bool
    {
        $envPath = base_path('.env');
        if (! is_file($envPath) || ! is_readable($envPath) || ! is_writable($envPath)) {
            return false;
        }
        $contents = file_get_contents($envPath);
        if ($contents === false) {
            return false;
        }
        $lines = preg_split('/(\\r\\n|\\n|\\r)/', $contents);
        $indices = [];
        foreach ($lines as $i => $line) {
            $trim = ltrim($line);
            if ($trim === '' || str_starts_with($trim, '#')) {
                continue;
            }
            $pos = strpos($trim, '=');
            if ($pos === false) {
                continue;
            }
            $key = substr($trim, 0, $pos);
            $indices[$key] = $i;
        }

        foreach ($changes as $key => $value) {
            $val = $this->escapeEnvValue($value);
            if (array_key_exists($key, $indices)) {
                $idx = $indices[$key];
                $lines[$idx] = $key.'='.$val;
            } else {
                $lines[] = $key.'='.$val;
            }
        }

        $final = implode(PHP_EOL, $lines).PHP_EOL;

        return file_put_contents($envPath, $final) !== false;
    }

    protected function escapeEnvValue(string $value): string
    {
        if (preg_match('/\\s|\\#|\\=|\\:/', $value)) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }
}
