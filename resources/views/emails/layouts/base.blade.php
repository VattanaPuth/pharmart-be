<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Notification' }}</title>
</head>

<body style="font-family: Arial, sans-serif; background:#f5f5f5; padding:20px;">

    <div style="max-width:600px;margin:auto;background:#fff;padding:20px;border-radius:10px;">

        <h2 style="color:#333;">
            {{ $title ?? 'Notification' }}
        </h2>

        <hr style="margin:15px 0;">

        <div style="font-size:14px;color:#444;">
            {!! $content !!}
        </div>

        <hr style="margin:20px 0;">

        <p style="font-size:12px;color:#999;">
            © {{ date('Y') }} Pharmart System
        </p>

    </div>

</body>
</html>