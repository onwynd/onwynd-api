<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class DeployPostInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy:post-install {--force : Force run even in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run post-deployment tasks including audio library seeding';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Running post-deployment installation tasks...');

        try {
            // Run migrations
            $this->info('Running migrations...');
            Artisan::call('migrate', ['--force' => true]);
            $this->info('Migrations completed.');

            // Seed audio library content
            $this->info('Seeding audio library content...');
            Artisan::call('db:seed', ['--class' => 'AudioLibrarySeeder', '--force' => true]);
            $this->info('Audio library seeding completed.');

            // Clear and rebuild caches
            $this->info('Clearing caches...');
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            $this->info('Caches cleared.');

            // Optimize for production
            if (app()->environment('production')) {
                $this->info('Optimizing for production...');
                Artisan::call('config:cache');
                Artisan::call('route:cache');
                Artisan::call('view:cache');
                $this->info('Production optimization completed.');
            }

            // Restart queue workers if they exist
            $this->info('Restarting queue workers...');
            try {
                Artisan::call('queue:restart');
                $this->info('Queue workers restarted.');
            } catch (\Exception $e) {
                $this->warn('Queue restart skipped (no workers running)');
            }

            Log::info('Post-deployment installation completed successfully');
            $this->info('✅ Post-deployment installation completed successfully!');

        } catch (\Exception $e) {
            Log::error('Post-deployment installation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error('❌ Post-deployment installation failed: '.$e->getMessage());

            return 1;
        }

        return 0;
    }
}
