<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Get all roles
        $adminRole = Role::where('slug', 'admin')->first();
        $therapistRole = Role::where('slug', 'therapist')->first();
        $customerRole = Role::where('slug', 'customer')->first();
        $managerRole = Role::where('slug', 'manager')->first();
        $dataEntryRole = Role::where('slug', 'data_entry')->first();

        if ($adminRole) {
            // Admin has all permissions
            $adminRole->update([
                'permissions' => json_encode([
                    'view_users',
                    'edit_users',
                    'delete_users',
                    'view_roles',
                    'edit_roles',
                    'view_reports',
                    'export_reports',
                    'manage_content',
                    'manage_appointments',
                    'manage_payments',
                    'view_analytics',
                    'manage_therapists',
                    'manage_institutions',
                ]),
            ]);
        }

        if ($therapistRole) {
            // Therapist permissions
            $therapistRole->update([
                'permissions' => json_encode([
                    'view_appointments',
                    'manage_own_appointments',
                    'view_own_earnings',
                    'view_patients',
                    'manage_availability',
                    'view_own_profile',
                ]),
            ]);
        }

        $clinicalAdvisorRole = Role::where('slug', 'clinical_advisor')->first();
        if ($clinicalAdvisorRole) {
            // Clinical Advisor permissions
            $clinicalAdvisorRole->update([
                'permissions' => json_encode([
                    'view_users',
                    'manage_therapists',
                    'view_reports',
                ]),
            ]);
        }

        if ($customerRole) {
            // Customer/Patient permissions
            $customerRole->update([
                'permissions' => json_encode([
                    'view_own_appointments',
                    'book_appointments',
                    'view_own_profile',
                    'view_therapists',
                    'leave_reviews',
                    'view_own_wellness',
                ]),
            ]);
        }

        if ($managerRole) {
            // Manager permissions
            $managerRole->update([
                'permissions' => json_encode([
                    'manage_content',
                    'view_reports',
                    'view_analytics',
                    'view_users',
                    'manage_appointments',
                ]),
            ]);
        }

        if ($dataEntryRole) {
            // Data Entry Specialist permissions
            $dataEntryRole->update([
                'permissions' => json_encode([
                    'manage_content',
                ]),
            ]);
        }
    }
}
