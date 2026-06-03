<?php

namespace App\Http\Controllers\NotificationControllers;

use App\Http\Controllers\Controller;
use App\Services\NotificationServices\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    private function currentCustomerId(Request $request): ?int
    {
        return $request->user()?->customer?->id;
    }

    /**
     * GET /api/notifications
     * Query params: is_read (0|1), type, from (Y-m-d), to (Y-m-d), per_page
     */
    public function index(Request $request): JsonResponse
    {
        $customerId = $this->currentCustomerId($request);

        if (!$customerId) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        $filters = $request->only(['is_read', 'type', 'from', 'to', 'per_page']);

        $paginator = $this->notificationService->getNotifications($customerId, $filters);

        return response()->json(['data' => $paginator]);
    }

    /**
     * GET /api/notifications/{notificationId}
     */
    public function show(Request $request, int $notificationId): JsonResponse
    {
        $customerId = $this->currentCustomerId($request);

        if (!$customerId) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        $notification = $this->notificationService->getNotificationById($customerId, $notificationId);

        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        return response()->json(['data' => $notification]);
    }

    /**
     * PUT /api/notifications/{notificationId}/read
     */
    public function markRead(Request $request, int $notificationId): JsonResponse
    {
        $customerId = $this->currentCustomerId($request);

        if (!$customerId) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        try {
            $notification = $this->notificationService->markAsRead($customerId, $notificationId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'message' => 'Notification marked as read',
            'data'    => $notification,
        ]);
    }

    /**
     * PUT /api/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $customerId = $this->currentCustomerId($request);

        if (!$customerId) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        $count = $this->notificationService->markAllAsRead($customerId);

        return response()->json([
            'message' => "{$count} notification(s) marked as read",
            'updated' => $count,
        ]);
    }

    /**
     * GET /api/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $customerId = $this->currentCustomerId($request);

        if (!$customerId) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        return response()->json([
            'unread_count' => $this->notificationService->getUnreadCount($customerId),
        ]);
    }

    public function unread(Request $request): JsonResponse
{
    $customerId = $this->currentCustomerId($request);

    if (!$customerId) {
        return response()->json(['message' => 'Customer profile not found'], 404);
    }

    $paginator = $this->notificationService->getNotifications($customerId, [
        'is_read' => 0,
        'per_page' => $request->input('per_page', 10),
    ]);

    return response()->json(['data' => $paginator]);
}
}
