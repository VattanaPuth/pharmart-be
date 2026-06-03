<?php

namespace App\Http\Controllers\AdminControllers\notificationController;

use App\Http\Controllers\Controller;
use App\Models\Admin\AdminNotification;
use Carbon\Carbon;

class AdminNotificationController extends Controller
{
    public function index()
    {
    
        $notifications = AdminNotification::query()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json($notifications);
    }

    public function unreadCount()
    {
    
        $count = AdminNotification::query()
           
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    public function markRead(AdminNotification $notification)
    {
       
        if (!$notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => Carbon::now(),
            ]);
        }

        return response()->json($notification->fresh());
    }

    public function markAllRead()
    {
       

        AdminNotification::query()
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => Carbon::now(),
            ]);

        return response()->json(['message' => 'All notifications marked as read']);
    }
}
