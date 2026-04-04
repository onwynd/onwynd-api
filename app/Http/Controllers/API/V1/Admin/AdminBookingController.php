<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Admin\AdminLog;
use App\Models\CenterServiceBooking;
use App\Models\PhysicalCenter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBookingController extends BaseController
{
    /**
     * Display booking overview dashboard.
     */
    public function overview(Request $request)
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        // Overall statistics
        $stats = [
            'total_bookings' => CenterServiceBooking::count(),
            'today_bookings' => CenterServiceBooking::whereDate('scheduled_at', $today)->count(),
            'this_week_bookings' => CenterServiceBooking::where('scheduled_at', '>=', $thisWeek)->count(),
            'this_month_bookings' => CenterServiceBooking::where('scheduled_at', '>=', $thisMonth)->count(),
            'completed_bookings' => CenterServiceBooking::where('status', 'completed')->count(),
            'pending_bookings' => CenterServiceBooking::where('status', 'pending')->count(),
            'cancelled_bookings' => CenterServiceBooking::where('status', 'cancelled')->count(),
        ];

        // Today's bookings with details
        $todayBookings = CenterServiceBooking::query()
            ->whereDate('scheduled_at', $today)
            ->with(['center:id,name', 'service:id,service_type', 'patient:id,name', 'therapist:id,name'])
            ->orderBy('scheduled_at')
            ->get()
            ->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'uuid' => $booking->uuid,
                    'center_name' => $booking->center->name,
                    'service_type' => $booking->service->service_type,
                    'patient_name' => $booking->patient->name,
                    'therapist_name' => $booking->therapist->name ?? 'Unassigned',
                    'scheduled_at' => $booking->scheduled_at->format('Y-m-d H:i'),
                    'status' => $booking->status,
                    'room_number' => $booking->room_number,
                    'payment_status' => $booking->payment_status,
                ];
            });

        // Recent bookings
        $recentBookings = CenterServiceBooking::query()
            ->with(['center:id,name', 'service:id,service_type', 'patient:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'uuid' => $booking->uuid,
                    'center_name' => $booking->center->name,
                    'service_type' => $booking->service->service_type,
                    'patient_name' => $booking->patient->name,
                    'scheduled_at' => $booking->scheduled_at->format('Y-m-d H:i'),
                    'status' => $booking->status,
                    'payment_status' => $booking->payment_status,
                    'created_at' => $booking->created_at->diffForHumans(),
                ];
            });

        return $this->sendResponse([
            'stats' => $stats,
            'today_bookings' => $todayBookings,
            'recent_bookings' => $recentBookings,
        ], 'Booking overview retrieved successfully.');
    }

    /**
     * Get booking by center.
     */
    public function byCenter(Request $request)
    {
        $centerStats = PhysicalCenter::query()
            ->select('id', 'name', 'capacity')
            ->withCount([
                'bookings as total_bookings',
                'bookings as today_bookings' => function ($query) {
                    $query->whereDate('scheduled_at', Carbon::today());
                },
                'bookings as completed_bookings' => function ($query) {
                    $query->where('status', 'completed');
                },
                'bookings as pending_bookings' => function ($query) {
                    $query->where('status', 'pending');
                },
                'bookings as cancelled_bookings' => function ($query) {
                    $query->where('status', 'cancelled');
                },
            ])
            ->get()
            ->map(function ($center) {
                $occupancyRate = $center->capacity > 0
                    ? round(($center->today_bookings / $center->capacity) * 100, 1)
                    : 0;

                return [
                    'center_id' => $center->id,
                    'center_name' => $center->name,
                    'capacity' => $center->capacity,
                    'total_bookings' => $center->total_bookings,
                    'today_bookings' => $center->today_bookings,
                    'completed_bookings' => $center->completed_bookings,
                    'pending_bookings' => $center->pending_bookings,
                    'cancelled_bookings' => $center->cancelled_bookings,
                    'occupancy_rate' => $occupancyRate,
                ];
            });

        return $this->sendResponse($centerStats, 'Center booking statistics retrieved successfully.');
    }

    /**
     * Get booking trends.
     */
    public function trends(Request $request)
    {
        $period = $request->input('period', '30days');
        $dateFormat = $period === '7days' ? 'Y-m-d' : 'Y-m';
        $groupBy = $period === '7days' ? 'DATE(scheduled_at)' : 'DATE_FORMAT(scheduled_at, "%Y-%m")';

        $bookingTrends = CenterServiceBooking::query()
            ->select(
                DB::raw("$groupBy as period"),
                DB::raw('COUNT(*) as total_bookings'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_bookings'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_bookings'),
                DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_bookings')
            )
            ->where('scheduled_at', '>=', Carbon::now()->subDays($period === '7days' ? 7 : 365))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $this->sendResponse($bookingTrends, 'Booking trends retrieved successfully.');
    }

    /**
     * Update booking status.
     */
    public function updateStatus(Request $request, CenterServiceBooking $booking)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled,no-show',
            'notes' => 'nullable|string|max:1000',
        ]);

        $oldStatus = $booking->status;
        $booking->update($validated);

        // Log the status change
        AdminLog::create([
            'user_id' => $request->user()->id,
            'action' => 'update_booking_status',
            'target_type' => CenterServiceBooking::class,
            'target_id' => $booking->id,
            'details' => [
                'old_status' => $oldStatus,
                'new_status' => $validated['status'],
                'booking_uuid' => $booking->uuid,
                'patient' => $booking->patient->name,
                'scheduled_at' => $booking->scheduled_at->format('Y-m-d H:i'),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->sendResponse($booking->load(['center:id,name', 'service:id,service_type', 'patient:id,name']), 'Booking status updated successfully.');
    }

    /**
     * Get booking details.
     */
    public function show(CenterServiceBooking $booking)
    {
        $booking->load([
            'center:id,name,city,address_line1',
            'service:id,service_type,description,duration_minutes,price',
            'patient:id,name,email,phone',
            'therapist:id,name,email,phone',
        ]);

        return $this->sendResponse($booking, 'Booking details retrieved successfully.');
    }

    /**
     * Get today's occupancy by hour.
     */
    public function occupancyByHour(Request $request)
    {
        $today = Carbon::today();
        $hourlyOccupancy = [];

        for ($hour = 8; $hour <= 20; $hour++) {
            $startTime = $today->copy()->setHour($hour)->setMinute(0);
            $endTime = $startTime->copy()->addHour();

            $bookingsCount = CenterServiceBooking::query()
                ->whereDate('scheduled_at', $today)
                ->where('scheduled_at', '>=', $startTime)
                ->where('scheduled_at', '<', $endTime)
                ->count();

            $hourlyOccupancy[] = [
                'hour' => $hour,
                'time_range' => $startTime->format('H:i').' - '.$endTime->format('H:i'),
                'bookings_count' => $bookingsCount,
            ];
        }

        return $this->sendResponse($hourlyOccupancy, 'Hourly occupancy retrieved successfully.');
    }
}
