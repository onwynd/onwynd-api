<?php

namespace App\Services\Institutional;

use App\Models\Institutional\Organization;
use App\Models\Institutional\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BulkImportService
{
    public function importMembers(Organization $organization, $file)
    {
        $data = [];
        if ($file instanceof \Illuminate\Http\UploadedFile) {
            $handle = fopen($file->getRealPath(), 'r');
            $header = fgetcsv($handle);

            // Normalize header keys
            $header = array_map(function ($h) {
                return strtolower(trim(str_replace([' ', '-'], '_', $h)));
            }, $header);

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) === count($header)) {
                    $data[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        } elseif (is_array($file)) {
            $data = $file;
        }

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($data as $index => $row) {
            DB::beginTransaction();
            try {
                $email = $row['email'] ?? null;
                if (! $email) {
                    throw new \Exception('Email is required');
                }

                // Check if user exists
                $user = User::where('email', $email)->first();
                if (! $user) {
                    $user = User::create([
                        'name' => $row['name'] ?? explode('@', $email)[0],
                        'email' => $email,
                        'password' => Hash::make(Str::random(16)), // Temporary password
                        'email_verified_at' => now(), // Assume verified if imported by org
                    ]);
                    $user->assignRole('patient'); // Default role
                }

                // Check if already a member
                if ($organization->members()->where('user_id', $user->id)->exists()) {
                    throw new \Exception('User is already a member');
                }

                OrganizationMember::create([
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'role' => $row['role'] ?? 'member',
                    'employee_id' => $row['employee_id'] ?? null,
                    'department' => $row['department'] ?? null,
                ]);

                DB::commit();
                $results['success']++;
            } catch (\Exception $e) {
                DB::rollBack();
                $results['failed']++;
                $results['errors'][] = 'Row '.($index + 1).': '.$e->getMessage();
            }
        }

        return $results;
    }
}
