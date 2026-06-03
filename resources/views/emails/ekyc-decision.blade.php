<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin:0; padding:0;">
@php
    $isApproved = ($decision === 'approved');
    $mainColor = $isApproved ? '#4CAF50' : '#f44336';
    $statusText = $isApproved
        ? 'Your eKYC application has been approved!'
        : 'Your eKYC application has been reviewed';
@endphp

    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: {{ $mainColor }}; color: #fff; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;">
            <h1 style="margin: 0;">eKYC Application Status</h1>
        </div>

        <div style="background-color: #f9f9f9; padding: 20px; border-radius: 0 0 5px 5px;">
            <p style="margin-top:0;">Hello {{ $ownerName }},</p>

            <div style="font-size: 24px; font-weight: bold; margin: 10px 0;">
                {{ $statusText }}
            </div>

            <div style="background-color: #fff; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid {{ $mainColor }};">
                <p style="margin: 0 0 8px;"><strong>Pharmacy Name:</strong> {{ $ekyc->pharmacy_name ?? 'N/A' }}</p>
                <p style="margin: 0 0 8px;"><strong>Status:</strong> {{ ucfirst($decision) }}</p>
                <p style="margin: 0;"><strong>Reviewed At:</strong> {{ $ekyc->reviewed_at?->format('F d, Y h:i A') ?? 'N/A' }}</p>
            </div>

            @if($isApproved)
                <p>Congratulations! Your pharmacy profile has been verified and approved. You can now access all features of your account.</p>
            @else
                <p>Your application has been reviewed. Please contact support if you need more information or wish to resubmit your application.</p>
            @endif

            <p>Thank you for your patience.</p>

            <p style="margin-bottom:0;">
                Best regards,<br>
                <strong>Pharmart Team</strong>
            </p>
        </div>

        <div style="text-align: center; margin-top: 20px; color: #666; font-size: 12px;">
            <p style="margin:0;">This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>