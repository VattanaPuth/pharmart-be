<?php

namespace App\Enums\Notification;

enum NotificationType: string
{
    // Order lifecycle
    case ORDER_PENDING   = 'order_pending';
    case ORDER_CONFIRMED = 'order_confirmed';
    case ORDER_READY     = 'order_ready';
    case ORDER_DELIVERING = 'order_delivering';
    case ORDER_PROCESSING_COMPLETION = 'order_processing_completion';
    case ORDER_COMPLETED = 'order_completed';
    case ORDER_CANCELLED = 'order_cancelled';
    case ORDER_DECLINED = 'order_declined';

        // Refund lifecycle
    case REFUND_REQUESTED     = 'refund_requested';
    case REFUND_UNDER_REVIEW  = 'refund_under_review';
    case REFUND_APPROVED      = 'refund_approved';
    case REFUND_RETURNING = 'refund_returning';
    case REFUND_RETURNED      = 'items_returned';
    case REFUND_REJECTED      = 'refund_rejected';
    case REFUND_VERIFIED     = 'refund_verified';
    case REFUND_PROCESSED     = 'refund_processed';
    case REFUND_FAILED        = 'refund_failed';
     case REFUND_COMPLETED = 'refund_completed';

    public function label(): string
    {
        return match ($this) {
            self::ORDER_PENDING       => 'Order Received',
            self::ORDER_CONFIRMED     => 'Order Confirmed',
            self::ORDER_READY         => 'Order Ready',
            self::ORDER_PROCESSING_COMPLETION => 'Awaiting Customer Confirmation',
            self::ORDER_COMPLETED  => 'Order Completed',
            self::ORDER_CANCELLED  => 'Order Cancelled',
            self::ORDER_DELIVERING => 'Out for Delivery',
            self::ORDER_DECLINED => 'Order Declined',

            self::REFUND_REQUESTED    => 'Refund Requested',
            self::REFUND_UNDER_REVIEW => 'Refund Under Review',
            self::REFUND_APPROVED     => 'Refund Approved',
            self::REFUND_REJECTED     => 'Refund Rejected',
            self::REFUND_PROCESSED    => 'Refund Processed',
            self::REFUND_FAILED       => 'Refund Failed',
        };
    }

    public function isOrder(): bool
    {
        return str_starts_with($this->value, 'order_');
    }

    public function isRefund(): bool
    {
        return str_starts_with($this->value, 'refund_');
    }

    public function requiresOrderId(): bool
    {
        return $this->isOrder();
    }

    public function requiresRefundId(): bool
    {
        return $this->isRefund();
    }
}
