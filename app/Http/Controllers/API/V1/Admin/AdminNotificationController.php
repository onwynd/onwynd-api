<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\Notification;
use Illuminate\Http\Request;

class AdminNotificationController extends BaseController
{
    public function index(Request $request)
    {
        // Default to the authenticated admin's own notifications
        $userId = $request->input('user_id', auth()->id());

        $query = Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($request->has('is_read')) {
            $query->where('is_read', filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN));
        }

        return $this->sendResponse($query->paginate(20), 'Admin notifications retrieved successfully.');
    }

    public function unreadCount(Request $request)
    {
        $userId = $request->input('user_id', auth()->id());

        $count = Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();

        return $this->sendResponse(['count' => $count], 'Unread admin notification count retrieved.');
    }

    public function markAsRead(Request $request, $id = null)
    {
        if ($id) {
            $notification = Notification::find($id);
            if (! $notification) {
                return $this->sendError('Notification not found.');
            }
            $notification->update(['is_read' => true, 'read_at' => now()]);
        } else {
            // Mark all as read for the current admin
            Notification::where('user_id', auth()->id())
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);
        }

        return $this->sendResponse([], 'Notification(s) marked as read.');
    }

    public function destroy(Request $request, $id)
    {
        $notification = Notification::find($id);

        if (! $notification) {
            return $this->sendError('Notification not found.');
        }

        $notification->delete();

        return $this->sendResponse([], 'Notification deleted successfully.');
    }
}
