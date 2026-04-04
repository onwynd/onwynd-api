<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ExecutiveUserSeeder extends Seeder
{
    /**
     * Seed the CEO and COO executive accounts.
     *
     * Run this seeder standalone to add executives without re-seeding the full DB:
     *   php artisan db:seed --class=ExecutiveUserSeeder
     *
     * Safe to run multiple times — uses firstOrCreate on email.
     */
    public function run(): void
    {
        $executives = [
            [
                'role_slug' => 'ceo',
                'email' => 'chijioke@onwynd.com',
                'first_name' => 'Chijioke',
                'last_name' => '',
            ],
            [
                'role_slug' => 'coo',
                'email' => 'happiness@onwynd.com',
                'first_name' => 'Happiness',
                'last_name' => '',
            ],
        ];

        foreach ($executives as $exec) {
            $role = Role::where('slug', $exec['role_slug'])->first();

            if (! $role) {
                $this->command->warn("Role '{$exec['role_slug']}' not found — run RoleSeeder first.");

                continue;
            }

            $user = User::withoutEvents(fn () => User::firstOrCreate(
                ['email' => $exec['email']],
                [
                    'uuid' => (string) Str::uuid(),
                    'role_id' => $role->id,
                    'first_name' => $exec['first_name'],
                    'last_name' => $exec['last_name'],
                    'password' => Hash::make('Onwynd@2025!'),
                    'gender' => 'prefer_not_to_say',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            ));

            // Sync role relation if the user already existed with a different role
            if ($user->role_id !== $role->id) {
                $user->update(['role_id' => $role->id]);
            }

            // Sync Spatie/roles relation if the project uses it
            if (method_exists($user, 'roles')) {
                try {
                    $user->roles()->sync([$role->id]);
                } catch (\Throwable) {
                    // roles() may be a belongsTo, not a pivot — silently skip
                }
            }

            $this->command->info("Executive seeded: {$exec['email']} ({$exec['role_slug']})");
        }
    }
}
