<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GetUserUuid extends Command
{
    protected $signature = 'user:get-uuid {user_id}';

    protected $description = 'Output the uuid for a given user id as JSON';

    public function handle(): int
    {
        $user = User::find((int) $this->argument('user_id'));
        if (! $user) {
            $this->line(json_encode(['error' => 'User not found']));

            return self::FAILURE;
        }
        $this->line(json_encode(['uuid' => $user->uuid]));

        return self::SUCCESS;
    }
}
