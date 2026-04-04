<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateReverbKeys extends Command
{
    protected $signature = 'reverb:generate-keys';

    protected $description = 'Generate REVERB_APP_ID, REVERB_APP_KEY and REVERB_APP_SECRET and write them to .env';

    public function handle(): int
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            $this->error('.env not found at '.$envPath);

            return self::FAILURE;
        }

        $appId = (string) Str::uuid();
        $appKey = bin2hex(random_bytes(16));
        $appSecret = bin2hex(random_bytes(32));

        $env = file_get_contents($envPath) ?: '';
        $env = $this->setEnv($env, 'REVERB_APP_ID', $appId);
        $env = $this->setEnv($env, 'REVERB_APP_KEY', $appKey);
        $env = $this->setEnv($env, 'REVERB_APP_SECRET', $appSecret);

        $ok = file_put_contents($envPath, $env);
        if ($ok === false) {
            $this->error('Failed to write .env');

            return self::FAILURE;
        }

        $this->info('Reverb credentials generated:');
        $this->line('REVERB_APP_ID='.$appId);
        $this->line('REVERB_APP_KEY='.$appKey);
        $this->line('REVERB_APP_SECRET='.$appSecret);
        $this->line('Update applied to .env');

        return self::SUCCESS;
    }

    private function setEnv(string $env, string $key, string $value): string
    {
        $pattern = '/^'.preg_quote($key, '/').'\s*=\s*.*$/m';
        if (preg_match($pattern, $env)) {
            return preg_replace($pattern, $key.'='.$value, $env);
        }
        $separator = str_ends_with($env, "\n") ? '' : "\n";

        return $env.$separator.$key.'='.$value."\n";
    }
}
