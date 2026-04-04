<?php

namespace App\Http\Controllers\API\V1\Secretary;

use App\Http\Controllers\API\BaseController;
use App\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VisitorController extends BaseController
{
    /**
     * Display a listing of visitors.
     */
    public function index(Request $request)
    {
        // Add filtering logic
        $query = Visitor::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('host', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->date_from)->startOfDay(),
                Carbon::parse($request->date_to)->endOfDay(),
            ]);
        } elseif ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        } elseif (! $request->has('all_time')) {
            // Default to today unless 'all_time' is requested
            $query->whereDate('created_at', Carbon::today());
        }

        $visitors = $query->orderBy('created_at', 'desc')->paginate(15);

        return $this->sendResponse($visitors, 'Visitors retrieved successfully.');
    }

    /**
     * Check-in a new visitor.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'purpose' => 'required|string',
            'host' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $visitor = Visitor::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'purpose' => $request->purpose,
            'host' => $request->host,
            'check_in_time' => Carbon::now(),
            'status' => 'checked_in',
        ]);

        return $this->sendResponse($visitor, 'Visitor checked in successfully', 201);
    }

    /**
     * Check-out a visitor.
     */
    public function update(Request $request, $id)
    {
        $visitor = Visitor::find($id);

        if (! $visitor) {
            return $this->sendError('Visitor not found.');
        }

        if ($request->has('action') && $request->action === 'checkout') {
            $visitor->update([
                'check_out_time' => Carbon::now(),
                'status' => 'checked_out',
            ]);

            return $this->sendResponse($visitor, 'Visitor checked out successfully');
        }

        // Handle other updates if needed
        $visitor->update($request->only(['name', 'purpose', 'host']));

        return $this->sendResponse($visitor, 'Visitor updated successfully');
    }

    public function checkout(Request $request, $id)
    {
        $visitor = Visitor::find($id);

        if (! $visitor) {
            return $this->sendError('Visitor not found.');
        }

        $visitor->update([
            'check_out_time' => Carbon::now(),
            'status' => 'checked_out',
        ]);

        return $this->sendResponse($visitor, 'Visitor checked out successfully');
    }

    /**
     * Remove the specified visitor log.
     */
    public function destroy($id)
    {
        $visitor = Visitor::find($id);
        if (! $visitor) {
            return $this->sendError('Visitor not found.');
        }

        $visitor->delete();

        return $this->sendResponse([], 'Visitor log deleted successfully');
    }
}
