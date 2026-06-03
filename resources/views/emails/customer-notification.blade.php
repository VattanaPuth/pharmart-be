<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;">
@php
    use App\Enums\Notification\NotificationType;

    $type    = $notification->type;
    $isOrder  = $type->requiresOrder();
    $isRefund = $type->requiresRefund();

    $colorMap = [
        'order_pending'       => '#2196F3',
        'order_confirmed'     => '#4CAF50',
        'order_ready'         => '#009688',
        'order_complete'      => '#3F51B5',
        'order_declined'      => '#F44336',
        'refund_requested'    => '#FF9800',
        'refund_under_review' => '#FF9800',
        'refund_approved'     => '#4CAF50',
        'refund_rejected'     => '#F44336',
        'refund_processed'    => '#3F51B5',
        'refund_failed'       => '#F44336',
    ];

    $iconMap = [
        'order_pending'       => '🕐',
        'order_confirmed'     => '✅',
        'order_ready'         => '📦',
        'order_complete'      => '🎉',
        'order_declined'      => '❌',
        'refund_requested'    => '📋',
        'refund_under_review' => '🔍',
        'refund_approved'     => '✅',
        'refund_rejected'     => '❌',
        'refund_processed'    => '💰',
        'refund_failed'       => '⚠️',
    ];

    $accentColor = $colorMap[$type->value] ?? '#2196F3';
    $icon        = $iconMap[$type->value] ?? '🔔';
@endphp

<div style="max-width: 600px; margin: 30px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">

    {{-- Header --}}
    <div style="background-color: {{ $accentColor }}; color: #ffffff; padding: 28px 30px; text-align: center;">
        <div style="font-size: 40px; margin-bottom: 8px;">{{ $icon }}</div>
        <h1 style="margin: 0; font-size: 22px; font-weight: 700; letter-spacing: 0.3px;">
            {{ $notification->title }}
        </h1>
    </div>

    {{-- Body --}}
    <div style="padding: 28px 30px;">
        <p style="margin-top: 0; font-size: 15px; color: #555;">
            {{ $notification->message }}
        </p>

        {{-- Order details block --}}
        @if($isOrder && $notification->order_id)
        <div style="background-color: #f9f9f9; border-left: 4px solid {{ $accentColor }}; border-radius: 4px; padding: 16px; margin: 20px 0;">
            <p style="margin: 0 0 6px; font-size: 13px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Order Reference</p>
            <p style="margin: 0; font-size: 16px; font-weight: 700; color: #333;">#{{ $notification->order_id }}</p>
            <p style="margin: 8px 0 0; font-size: 13px; color: #888;">
                Status: <strong style="color: {{ $accentColor }};">{{ ucfirst(str_replace('_', ' ', $type->value)) }}</strong>
            </p>
        </div>
        @endif

        {{-- Refund details block --}}
        @if($isRefund && $notification->refund_id)
        <div style="background-color: #f9f9f9; border-left: 4px solid {{ $accentColor }}; border-radius: 4px; padding: 16px; margin: 20px 0;">
            <p style="margin: 0 0 6px; font-size: 13px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;">Refund Reference</p>
            <p style="margin: 0; font-size: 16px; font-weight: 700; color: #333;">#{{ $notification->refund_id }}</p>
            @if($notification->order_id)
            <p style="margin: 8px 0 0; font-size: 13px; color: #888;">
                Related Order: <strong>#{{ $notification->order_id }}</strong>
            </p>
            @endif
            <p style="margin: 8px 0 0; font-size: 13px; color: #888;">
                Status: <strong style="color: {{ $accentColor }};">{{ ucfirst(str_replace('_', ' ', $type->value)) }}</strong>
            </p>
        </div>
        @endif

        <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

        <p style="margin: 0; font-size: 14px; color: #666;">
            Received on {{ $notification->created_at->format('F d, Y \a\t h:i A') }}
        </p>

        <p style="margin: 20px 0 0; font-size: 14px;">
            Best regards,<br>
            <strong>Pharmart Team</strong>
        </p>
    </div>

    {{-- Footer --}}
    <div style="background-color: #f4f4f4; padding: 16px 30px; text-align: center; border-top: 1px solid #e8e8e8;">
        <p style="margin: 0; font-size: 12px; color: #aaa;">
            This is an automated message. Please do not reply to this email.
        </p>
    </div>

</div>
</body>
</html>
