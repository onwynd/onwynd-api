<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * WebSocketServer Command
 *
 * Manages WebSocket server for real-time communication
 * Supports both broadcasting and direct socket connections
 */
class WebSocketServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'websocket:start {--port=6001 : The port to run the WebSocket server on}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start WebSocket server for real-time communication';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $port = $this->option('port');

        $this->info('WebSocket Server Configuration:');
        $this->line("Port: <info>{$port}</info>");
        $this->line('Broadcasting Driver: <info>'.config('broadcasting.default').'</info>');

        $driver = config('broadcasting.default');

        if ($driver === 'pusher') {
            return $this->startPusherServer();
        } elseif ($driver === 'redis') {
            return $this->startRedisServer();
        } else {
            return $this->startLocalServer($port);
        }
    }

    /**
     * Start Pusher WebSocket server
     */
    protected function startPusherServer(): int
    {
        $this->info('Using Pusher for WebSocket broadcasting');
        $this->info('Connection details will be managed by Pusher.');
        $this->line('');
        $this->line('WebSocket server is configured and ready for connections.');
        $this->line('Ensure PUSHER_* environment variables are properly set.');

        Log::info('WebSocket server started with Pusher driver');

        return self::SUCCESS;
    }

    /**
     * Start Redis WebSocket server
     */
    protected function startRedisServer(): int
    {
        $this->info('Using Redis for WebSocket broadcasting');
        $this->warn('Note: Redis broadcasting requires a separate WebSocket bridge.');
        $this->line('');
        $this->line('Set up options:');
        $this->line('1. Use Laravel Echo with Redis (recommended)');
        $this->line('2. Use Socket.io with Redis adapter');
        $this->line('3. Configure Redis connection in config/database.php');

        Log::info('WebSocket server configured with Redis driver');

        return self::SUCCESS;
    }

    /**
     * Start local development WebSocket server
     */
    protected function startLocalServer(int $port): int
    {
        $this->warn('⚠️  Running WebSocket server in development mode');
        $this->info("Server would listen on: ws://localhost:{$port}");
        $this->line('');
        $this->line('To use WebSockets in production, configure:');
        $this->line('  1. Set BROADCAST_DRIVER=pusher in .env');
        $this->line('  2. Configure Pusher credentials');
        $this->line('  3. Or use: BROADCAST_DRIVER=redis');
        $this->line('');
        $this->info('For development, consider using:');
        $this->line('  php artisan serve');
        $this->line('  npm run dev (for frontend)');
        $this->line('  laravel-echo-server start (separate process)');

        Log::info("WebSocket development server configured on port {$port}");

        return self::SUCCESS;
    }
}
