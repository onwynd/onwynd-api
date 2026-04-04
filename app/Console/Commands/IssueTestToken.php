<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IssueTestToken extends Command
{
    protected $signature = 'auth:issue-test-token';

    protected $description = 'Create or find a test user and print a Sanctum token for API testing';

    public function handle(): int
    {
        $user = User::query()->first();
        if (! $user) {
            $role = Role::where('slug', 'patient')->first();
            if (! $role) {
                $role = Role::create([
                    'name' => 'Patient',
                    'slug' => 'patient',
                    'permissions' => [],
                ]);
            }
            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test+'.time().'@example.com',
                'password' => 'password',
                'is_active' => true,
                'role_id' => $role->id,
                'gender' => 'other',
            ]);
        }

        $token = $user->createToken('test')->plainTextToken;
        $this->info('User ID: '.$user->id);
        $this->line('Bearer '.$token);

        return self::SUCCESS;
    }
}
