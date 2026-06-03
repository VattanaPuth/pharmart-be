<?php

namespace App\Services\NotificationServices\impl;

use App\Models\Owner\OwnerNotification;
use App\Models\Admin\AdminNotification;
use App\Models\Notification\Notification;
use Illuminate\Support\Facades\Mail;
use App\Mail\Base\MailRouter;
use App\Services\NotificationServices\NotificationService;
use RuntimeException;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationServiceImpl implements NotificationService
{
    // =========================
    // OWNER
    // =========================
    public static function owner($owner, string $type, array $data = [])
    {
        $notification = OwnerNotification::create([
            'owner_id' => $owner->id,
            'customer_id' => $data['customer_id'] ?? null,
            'order_number' => $data['order_number'] ?? null,
            'order_id' => $data['order_id'] ?? null,
            'refund_id' => $data['refund_id'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'type' => $type,
            'title' => $data['title'] ?? '',
            'message' => $data['message'] ?? '',
            'data' => $data,
            'total' => $data['total'] ?? null,
            'channels' => $data['channels'] ?? ['database', 'mail'],
        ]);

        $email = $owner->setting?->displayable_email ?? $owner->register?->email;

        if ($email) {
            self::handleMail($email, $notification);
        }

        return $notification;
    }

    // =========================
    // ADMIN
    // =========================
    public static function admin($admin, string $type, array $data = [])
    {
        $notification = AdminNotification::create([
            'admin_id'   => $admin->id,
            'owner_id'   => $data['owner_id'] ?? null,
            'owner_name' => $data['owner_name'] ?? null,
            'ekyc_id'    => $data['ekyc_id'] ?? null,
            'type'       => $type,
            'title'      => $data['title'] ?? '',
            'message'    => $data['message'] ?? '',
            'data'       => $data,
            'is_read'    => false,
            'channels'   => $data['channels'] ?? ['database', 'mail'],
        ]);
        $email = config('notification.admin_email');



        if ($email) {
            self::handleMail($email, $notification);
        }

        return $notification;
    }

    // =========================
    // CUSTOMER
    // =========================
    public static function customer($customer, string $type, array $data = [])
    {
        $notification = Notification::create([
            'customer_id' => $customer->id,
            'order_id' => $data['order_id'] ?? null,
            'refund_id' => $data['refund_id'] ?? null,
            'owner_id' => $data['owner_id'] ?? null,
            'type' => $type,
            'title' => $data['title'] ?? '',
            'message' => $data['message'] ?? '',
            'target_role' => 'customer',
        ]);

        self::handleMail($customer->information->email, $notification);

        return $notification;
    }

    // =========================
    // SINGLE MAIL HANDLER (NEW)
    // =========================
    private static function handleMail(string $email, $notification): void
    {
        if (!in_array('mail', $notification->channels ?? ['mail'])) {
            return;
        }

        Mail::to($email)->queue(
            new MailRouter(
                is_object($notification->type)
        ? $notification->type->value
        : $notification->type,
                $notification->data ?? []
            )
        );
    }


    //     public function createNotification(array $data): Notification
    // {
    //     $this->validatePayload($data);

    //     /** @var NotificationType $type */
    //     $type = $data['type'];

    //     $notification = Notification::query()->create([
    //         'customer_id' => (int) $data['customer_id'],
    //         'order_id'    => isset($data['order_id'])   ? (int) $data['order_id']   : null,
    //         'refund_id'   => isset($data['refund_id'])  ? (int) $data['refund_id']  : null,
    //         'owner_id'    => isset($data['owner_id'])   ? (int) $data['owner_id']   : null,
    //         'product_id'  => isset($data['product_id']) ? (int) $data['product_id'] : null,
    //         'type'        => $type->value,
    //         'title'       => $data['title'],
    //         'message'     => $data['message'],
    //         'target_role' => $data['target_role'] ?? 'customer',
    //         'is_read'     => false,
    //     ]);

    //     // Email is fire-and-forget — a delivery failure must never lose the DB record.
    //     try {
    //         $this->dispatchEmail($notification);
    //     } catch (\Throwable $e) {
    //         Log::error('CustomerNotificationMail failed', [
    //             'notification_id' => $notification->id,
    //             'error'           => $e->getMessage(),
    //         ]);
    //     }

    //     return $notification;
    // }

    public function getNotifications(
        int $customerId,
        array $filters
    ): LengthAwarePaginator {
        $query = Notification::query()
            ->where('customer_id', $customerId)
            ->latest();

        if (
            array_key_exists('is_read', $filters)
            && $filters['is_read'] !== null
        ) {
            $query->where(
                'is_read',
                filter_var(
                    $filters['is_read'],
                    FILTER_VALIDATE_BOOLEAN
                )
            );
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate(
                'created_at',
                '>=',
                $filters['from']
            );
        }

        if (!empty($filters['to'])) {
            $query->whereDate(
                'created_at',
                '<=',
                $filters['to']
            );
        }

        $perPage = min(
            (int) ($filters['per_page'] ?? 15),
            50
        );

        return $query->paginate($perPage);
    }

    public function getNotificationById(
        int $customerId,
        int $notificationId
    ): ?Notification {
        return Notification::query()
            ->where('id', $notificationId)
            ->where('customer_id', $customerId)
            ->first();
    }

    public function markAsRead(
        int $customerId,
        int $notificationId
    ): Notification {
        $notification = Notification::query()
            ->where('id', $notificationId)
            ->where('customer_id', $customerId)
            ->first();

        if (!$notification) {
            throw new RuntimeException(
                'Notification not found.'
            );
        }

        if (!$notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return $notification->fresh();
    }

    public function markAllAsRead(
        int $customerId
    ): int {
        return Notification::query()
            ->where('customer_id', $customerId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    public function getUnreadCount(
        int $customerId
    ): int {
        return Notification::query()
            ->where('customer_id', $customerId)
            ->where('is_read', false)
            ->count();
    }
}
