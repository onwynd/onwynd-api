<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();

        if ($adminRole) {
            User::withoutEvents(fn () => User::firstOrCreate(
                ['email' => 'admin@onwynd.com'],
                [
                    'uuid' => (string) Str::uuid(),
                    'role_id' => $adminRole->id,
                    'password' => Hash::make('password'),
                    'first_name' => 'Admin',
                    'last_name' => 'User',
                    'gender' => 'prefer_not_to_say',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            ));
        }
    }
}
