<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5fb;color:#25283d;font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f5fb;padding:32px 14px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #e5e7f2;border-radius:16px;overflow:hidden;box-shadow:0 12px 35px rgba(39,42,75,.08);">
            <tr>
                <td style="height:5px;background:linear-gradient(90deg,#6c5ce7,#7d6cf2,#20b7d6);"></td>
            </tr>
            <tr>
                <td style="padding:26px 34px 20px;border-bottom:1px solid #eceefa;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                        <tr>
                            <td>
                                <img src="{{ $branding->assetUrl('login_logo', 'assets/img/logo.png') }}" alt="{{ config('app.name') }}" style="display:block;max-width:180px;max-height:52px;width:auto;height:auto;">
                            </td>
                            <td align="right" style="font-size:11px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:#7465eb;">{{ $eyebrow }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="padding:32px 34px 8px;">
                    <div style="display:inline-block;padding:6px 11px;border-radius:999px;background:#f0edff;color:#6554dc;font-size:12px;font-weight:700;">{{ $badge }}</div>
                    <h1 style="margin:17px 0 10px;font-size:25px;line-height:1.3;color:#202237;">{{ $title }}</h1>
                    <p style="margin:0 0 13px;font-size:15px;line-height:1.7;color:#555a72;">Hello {{ $recipientName }},</p>
                    <p style="margin:0;font-size:15px;line-height:1.7;color:#555a72;">{{ $bodyText }}</p>
                </td>
            </tr>
            @if(!empty($details))
            <tr>
                <td style="padding:22px 34px 4px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e7e8f3;border-radius:12px;background:#fafaff;overflow:hidden;">
                        @foreach($details as $label => $value)
                        <tr>
                            <td style="padding:12px 15px;{{ !$loop->last ? 'border-bottom:1px solid #e7e8f3;' : '' }}font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#8a8ea5;width:30%;">{{ $label }}</td>
                            <td style="padding:12px 15px;{{ !$loop->last ? 'border-bottom:1px solid #e7e8f3;' : '' }}font-size:14px;font-weight:600;color:#30334b;">{{ $value }}</td>
                        </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
            @endif
            <tr>
                <td style="padding:27px 34px 30px;">
                    <a href="{{ $actionUrl }}" style="display:inline-block;padding:13px 22px;border-radius:9px;background:#6c5ce7;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;box-shadow:0 7px 18px rgba(108,92,231,.25);">{{ $actionLabel }} &nbsp;→</a>
                    @if(!empty($note))<p style="margin:22px 0 0;padding:13px 15px;border-left:3px solid #6c5ce7;border-radius:5px;background:#f7f6ff;font-size:13px;line-height:1.6;color:#686c83;">{{ $note }}</p>@endif
                </td>
            </tr>
            <tr>
                <td style="padding:20px 34px;background:#f8f8fc;border-top:1px solid #eceefa;text-align:center;font-size:12px;line-height:1.6;color:#9396aa;">
                    This is an automated message from {{ config('app.name') }}.<br>
                    If the button does not work, copy this link: <a href="{{ $actionUrl }}" style="color:#6c5ce7;text-decoration:none;word-break:break-all;">{{ $actionUrl }}</a>
                </td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>
