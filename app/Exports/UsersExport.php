<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * UsersExport
 *
 * Export class for users
 */
class UsersExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @var string|null
     */
    private $role;

    /**
     * Constructor
     */
    public function __construct($role = null)
    {
        $this->role = $role;
    }

    /**
     * Query
     */
    public function query()
    {
        $query = User::query();

        if ($this->role) {
            $query->where('role', $this->role);
        }

        return $query->orderByDesc('created_at');
    }

    /**
     * Headings
     */
    public function headings(): array
    {
        return [
            'User ID',
            'Full Name',
            'Email',
            'Phone',
            'Role',
            'Gender',
            'Country',
            'Status',
            'Email Verified',
            'Last Login',
            'Created Date',
        ];
    }

    /**
     * Map
     */
    public function map($user): array
    {
        return [
            $user->id,
            $user->full_name,
            $user->email,
            $user->phone ?? 'N/A',
            $user->role ?? 'user',
            $user->gender ?? 'N/A',
            $user->country ?? 'N/A',
            $user->status,
            $user->email_verified_at ? 'Yes' : 'No',
            $user->last_login?->format('Y-m-d H:i:s') ?? 'Never',
            $user->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
