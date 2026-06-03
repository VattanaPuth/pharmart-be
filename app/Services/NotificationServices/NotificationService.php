<?php

namespace App\Services\NotificationServices;

use App\Models\Notification\Notification;
use Illuminate\Pagination\LengthAwarePaginator;

interface NotificationService
{
    // public function createNotification(array $data): Notification;
    public function getNotifications(int $customerId, array $filters): LengthAwarePaginator;
    public function getNotificationById(int $customerId, int $notificationId): ?Notification;
    public function markAsRead(int $customerId, int $notificationId): Notification;
    public function markAllAsRead(int $customerId): int;
    public function getUnreadCount(int $customerId): int;
}

