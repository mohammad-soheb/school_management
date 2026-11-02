<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitation to {{ $schoolName }}</title>
</head>
<body style="margin:0;padding:24px;background:#f6f7fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #e4e8f0;border-radius:16px;">
        <tr>
            <td style="padding:32px;">
                <h1 style="margin:0 0 12px;font-size:20px;">You are invited to {{ $schoolName }}</h1>
                <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#4b5563;">
                    Create your login to start using {{ config('app.name') }}. This link works once and expires on
                    {{ $invitation->expires_at->timezone(config('app.display_timezone'))->format('d M Y') }}.
                </p>
                <p style="margin:0 0 24px;">
                    <a href="{{ $url }}" style="display:inline-block;padding:12px 22px;background:#4f46e5;color:#ffffff;border-radius:12px;text-decoration:none;font-weight:600;font-size:15px;">Accept invitation</a>
                </p>
                <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;">
                    If the button does not work, copy this address into your browser:<br>
                    <span style="word-break:break-all;">{{ $url }}</span>
                </p>
                <p style="margin:20px 0 0;font-size:13px;color:#6b7280;">If you were not expecting this, you can ignore this email.</p>
            </td>
        </tr>
    </table>
</body>
</html>
