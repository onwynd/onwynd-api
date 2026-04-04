<?php

namespace Database\Seeders;

use App\Models\Institutional\Organization;
use App\Models\Institutional\OrganizationMember;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * CorporateDemoSeeder
 *
 * Creates a realistic corporate demo environment:
 *  - 1 institutional admin (HR dashboard login)
 *  - 1 Organization (Acme Nigeria Ltd) on the Growth plan
 *  - 15 employee members with varied engagement states
 *
 * Run standalone:  php artisan db:seed --class=CorporateDemoSeeder
 */
class CorporateDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Get roles
        $institutionalRole = Role::where('slug', 'institutional')->first();
        $patientRole = Role::where('slug', 'patient')->first();

        if (!$institutionalRole || !$patientRole) {
            $this->command->error('Roles "institutional" or "patient" not found. Please run RoleSeeder first.');
            return;
        }

        // ── 1. Institutional admin user ────────────────────────────────────────
        $admin = User::withoutEvents(fn () => User::firstOrCreate(
            ['email' => 'hr@acmenigeria.com'],
            [
                'role_id' => $institutionalRole->id,
                'first_name' => 'Amaka',
                'last_name' => 'Okonkwo',
                'phone' => '+2348031234567',
                'password' => Hash::make('password'),
                'gender' => 'female',
                'email_verified_at' => now(),
            ]
        ));

        // ── 2. Organisation ────────────────────────────────────────────────────
        $org = Organization::firstOrCreate(
            ['contact_email' => 'hr@acmenigeria.com'],
            [
                'name' => 'Acme Nigeria Ltd',
                'type' => 'corporate',
                'domain' => 'acmenigeria.com',
                'status' => 'active',
                'subscription_plan' => 'growth',
                'max_members' => 100,
                'contracted_seats' => 50,
                'current_seats' => 15,
                'org_type' => 'corporate',
                'sso_config' => null,
            ]
        );

        // Add the admin as an org admin member
        OrganizationMember::firstOrCreate(
            ['organization_id' => $org->id, 'user_id' => $admin->id],
            ['role' => 'admin', 'department' => 'Human Resources']
        );

        // ── 3. Employee members ────────────────────────────────────────────────
        $employees = [
            ['first_name' => 'Chukwuemeka', 'last_name' => 'Eze',       'email' => 'c.eze@acmenigeria.com',       'department' => 'Engineering'],
            ['first_name' => 'Fatima',      'last_name' => 'Bello',     'email' => 'f.bello@acmenigeria.com',     'department' => 'Engineering'],
            ['first_name' => 'Tunde',       'last_name' => 'Adeyemi',   'email' => 't.adeyemi@acmenigeria.com',   'department' => 'Sales'],
            ['first_name' => 'Ngozi',       'last_name' => 'Obi',       'email' => 'n.obi@acmenigeria.com',       'department' => 'Sales'],
            ['first_name' => 'Seun',        'last_name' => 'Adesanya',  'email' => 's.adesanya@acmenigeria.com',  'department' => 'Operations'],
            ['first_name' => 'Kelechi',     'last_name' => 'Nwachukwu', 'email' => 'k.nwachukwu@acmenigeria.com', 'department' => 'Operations'],
            ['first_name' => 'Aisha',       'last_name' => 'Musa',      'email' => 'a.musa@acmenigeria.com',      'department' => 'Finance'],
            ['first_name' => 'Emeka',       'last_name' => 'Okoro',     'email' => 'e.okoro@acmenigeria.com',     'department' => 'Finance'],
            ['first_name' => 'Blessing',    'last_name' => 'Uche',      'email' => 'b.uche@acmenigeria.com',      'department' => 'Marketing'],
            ['first_name' => 'Yemi',        'last_name' => 'Omotosho',  'email' => 'y.omotosho@acmenigeria.com',  'department' => 'Marketing'],
            ['first_name' => 'Ifeoma',      'last_name' => 'Nwosu',     'email' => 'i.nwosu@acmenigeria.com',     'department' => 'Legal'],
            ['first_name' => 'Adebayo',     'last_name' => 'Fadahunsi', 'email' => 'a.fadahunsi@acmenigeria.com', 'department' => 'Legal'],
            ['first_name' => 'Chiamaka',    'last_name' => 'Nkem',      'email' => 'c.nkem@acmenigeria.com',      'department' => 'HR'],
            ['first_name' => 'Oluwaseun',   'last_name' => 'Bakare',    'email' => 'o.bakare@acmenigeria.com',    'department' => 'HR'],
            ['first_name' => 'Musa',        'last_name' => 'Garba',     'email' => 'm.garba@acmenigeria.com',     'department' => 'Engineering'],
        ];

        foreach ($employees as $idx => $emp) {
            $user = User::withoutEvents(fn () => User::firstOrCreate(
                ['email' => $emp['email']],
                [
                    'role_id' => $patientRole->id,
                    'first_name' => $emp['first_name'],
                    'last_name' => $emp['last_name'],
                    'password' => Hash::make('password'),
                    'gender' => 'other',
                    'email_verified_at' => now(),
                ]
            ));

            OrganizationMember::firstOrCreate(
                ['organization_id' => $org->id, 'user_id' => $user->id],
                [
                    'role' => 'member',
                    'department' => $emp['department'],
                    'employee_id' => 'EMP'.str_pad($idx + 1, 3, '0', STR_PAD_LEFT),
                    // Session tracking fields (added by 2026_03_10 migration)
                    // These will be ignored if the columns don't exist yet
                ]
            );
        }

        $this->command->info('CorporateDemoSeeder: Acme Nigeria Ltd created with '.count($employees).' employees.');
        $this->command->info('  HR admin login → hr@acmenigeria.com / password');
    }
}
