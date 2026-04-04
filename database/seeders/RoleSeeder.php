<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'patient',
            'customer',
            'therapist',
            'admin',
            'tech_team',
            'product_manager',
            'employee',
            'manager',
            'support',
            'sales',
            'marketing',
            'finance',
            'data_entry',
            'clinical_advisor',
            'investor',
            'closer', // Senior AE / Head of Sales
            'relationship_manager', // Builder
            // Additional roles used by dashboards and backend route groups
            'secretary',
            'hr',
            'ambassador',
            'legal_advisor',
            'partner',
            'health_personnel',
            'institutional',
            'compliance',
            // Executive roles
            'ceo',
            'coo',
            'cgo',
            'cfo',
            // Oversight tier
            'president',
            // VP tier
            'vp_sales',
            'vp_marketing',
            'vp_operations',
            'vp_product',
            // Audit role
            'audit',
            // Tech dashboard role (distinct from tech_team)
            'tech',
            // Platform oversight roles
            'super_admin',
            'founder',
            // Additional roles
            'finder',           // Sales prospecting (distinct from closer)
            'center',           // Center / clinic manager
            'institution_admin',
            'university_admin',
            'ngo_admin',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['slug' => $role],
                [
                    'name' => ucwords(str_replace('_', ' ', $role)),
                    'permissions' => json_encode([]),
                ]
            );
        }
    }
}
