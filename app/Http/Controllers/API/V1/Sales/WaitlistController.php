<?php

namespace App\Http\Controllers\API\V1\Sales;

use App\Http\Controllers\API\BaseController;
use App\Models\WaitlistSubmission;
use Illuminate\Http\Request;

class WaitlistController extends BaseController
{
    /**
     * GET /api/v1/sales/waitlist
     */
    public function index(Request $request)
    {
        $submissions = WaitlistSubmission::query()
            ->when($request->search, fn ($q, $s) => $q->where(function ($q2) use ($s) {
                $q2->where('email', 'like', "%{$s}%")
                   ->orWhere('first_name', 'like', "%{$s}%")
                   ->orWhere('last_name', 'like', "%{$s}%")
                   ->orWhere('country', 'like', "%{$s}%")
                   ->orWhere('referral_source', 'like', "%{$s}%");
            }))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->country, fn ($q, $c) => $q->where('country', $c))
            ->latest()
            ->paginate($request->per_page ?? 25);

        return $this->sendResponse($submissions, 'Waitlist retrieved.');
    }

    /**
     * GET /api/v1/sales/waitlist/export
     */
    public function export()
    {
        $rows = WaitlistSubmission::orderBy('created_at')->get();

        $csv = "ID,First Name,Last Name,Email,Role,Country,Referral Source,Message,Status,Joined At\n";
        foreach ($rows as $row) {
            $csv .= implode(',', [
                $row->id,
                '"'.$row->first_name.'"',
                '"'.$row->last_name.'"',
                $row->email,
                $row->role,
                $row->country ?? '',
                $row->referral_source ?? '',
                '"'.str_replace('"', '""', $row->message ?? '').'"',
                $row->status,
                $row->created_at->toDateTimeString(),
            ])."\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="waitlist-'.now()->format('Y-m-d').'"',
        ]);
    }
}
