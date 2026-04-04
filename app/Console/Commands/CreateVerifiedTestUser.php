<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateVerifiedTestUser extends Command
{
    protected $signature = 'user:create-verified-test {role=patient}';

    protected $description = 'Create a verified test user and output a Sanctum token in JSON';

    public function handle(): int
    {
        $email = 'test+'.Str::random(6).'@example.com';
        $roleInput = strtolower($this->argument('role'));

        $role = Role::where('slug', $roleInput)->first();
        if (! $role) {
            $role = Role::create([
                'name' => $roleInput === 'therapist' ? 'Therapist' : 'Patient',
                'slug' => $roleInput,
                'description' => $roleInput === 'therapist' ? 'Default therapist role' : 'Default patient role',
                'permissions' => [],
            ]);
        }
        $roleId = $role->id;

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => ucfirst($roleInput),
            'email' => $email,
            'password' => bcrypt('Password123!'),
            'role_id' => $roleId,
            'is_active' => true,
            'gender' => 'other',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->line(json_encode([
            'user_id' => $user->id,
            'email' => $user->email,
            'access_token' => $token,
        ]));

        return self::SUCCESS;
    }
}
