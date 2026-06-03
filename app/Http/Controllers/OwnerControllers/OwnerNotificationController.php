<?php

namespace App\Http\Controllers\OwnerControllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Owner\OwnerNotification;


class OwnerNotificationController extends Controller
{
    public function index(Request $request)
    {
        $ownerID = $request->user()->owner?->id;

        $notifications = OwnerNotification::query()
            ->where('owner_id', $ownerID)
            ->latest()
            ->paginate(20);

        return response()->json([
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(
        Request $request,
        int $id
    ) {
       $ownerID = $request->user()->owner?->id;

        $notification = OwnerNotification::query()
            ->where('owner_id',  $ownerID )
            ->findOrFail($id);

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'message' => 'Notification marked as read.',
        ]);
    }

    public function unreadCount(Request $request)
    {
          $ownerID = $request->user()->owner?->id;

        $count = OwnerNotification::query()
            ->where('owner_id',  $ownerID)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'count' => $count,
        ]);
    }

    public function markAllRead(Request $request)
{
     $ownerID = $request->user()->owner?->id;

    OwnerNotification::query()
        ->where('owner_id', $ownerID )
        ->where('is_read', false)
        ->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

    return response()->json([
        'message' => 'All notifications marked as read.',
    ]);
}
}