<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::all();
        $password = Hash::make('password');

        foreach ($roles as $role) {
            User::withoutEvents(fn () => User::firstOrCreate(
                ['email' => $role->slug.'@onwynd.com'],
                [
                    'uuid' => Str::uuid(),
                    'first_name' => $role->name,
                    'last_name' => 'User',
                    'password' => $password,
                    'role_id' => $role->id,
                    'is_active' => true,
                    'gender' => 'prefer_not_to_say',
                ]
            ));
        }
    }
}
