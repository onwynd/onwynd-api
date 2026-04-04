<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends BaseController
{
    public function index(Request $request)
    {
        $query = Notification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        // Optional filter: ?is_read=false to get only unread notifications
        if ($request->has('is_read')) {
            $query->where('is_read', filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN));
        }

        // Optional filter: ?type=session to get only specific types
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        return $this->sendResponse($query->paginate(20), 'Notifications retrieved successfully.');
    }

    public function markAsRead(Request $request, $id = null)
    {
        $user = $request->user();

        if ($id) {
            $notification = Notification::where('user_id', $user->id)->find($id);
            if (! $notification) {
                return $this->sendError('Notification not found.');
            }
            $notification->update(['is_read' => true, 'read_at' => now()]);
        } else {
            // Mark all as read
            Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);
        }

        return $this->sendResponse([], 'Notification(s) marked as read.');
    }

    public function unreadCount(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return $this->sendResponse(['count' => $count], 'Unread notification count retrieved.');
    }

    public function destroy($id)
    {
        $notification = Notification::where('user_id', auth()->id())->find($id);

        if (! $notification) {
            return $this->sendError('Notification not found.');
        }

        $notification->delete();

        return $this->sendResponse([], 'Notification deleted successfully.');
    }

    public function deleteAll(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->delete();

        return $this->sendResponse([], 'All notifications deleted successfully.');
    }
}
