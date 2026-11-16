<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Your Judibas invitation</title></head>
<body style="margin:0;padding:0;background-color:#f4f5f9;font-family:Arial,Helvetica,sans-serif;color:#202334;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">Your workspace is ready. Accept your invitation to join Judibas.</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f5f9;"><tr><td align="center" style="padding:36px 16px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:580px;">
<tr><td style="padding:0 0 22px;font-size:24px;font-weight:bold;letter-spacing:-0.5px;color:#6554e8;">Judibas<span style="font-size:12px;font-weight:normal;letter-spacing:0;color:#72788b;"> &nbsp; YOUR CONNECTED WORKSPACE</span></td></tr>
<tr><td style="background-color:#ffffff;border:1px solid #e4e6ef;border-top:4px solid #7762ef;border-radius:12px;padding:32px 28px;">
<p style="margin:0 0 14px;font-size:11px;font-weight:bold;letter-spacing:1.5px;color:#7762ef;">YOU'RE INVITED</p>
<h1 style="margin:0 0 18px;font-size:28px;line-height:1.25;letter-spacing:-0.5px;color:#202334;">Your next workspace starts here.</h1>
<p style="margin:0 0 12px;font-size:15px;line-height:1.7;">Hello{{ !empty($inviteeName) ? ' '.$inviteeName : '' }},</p>
<p style="margin:0 0 24px;font-size:15px;line-height:1.7;color:#606779;">You've been invited to join {{ $invitationCompany ?? 'Judibas' }}. Accept your invitation to access your assigned applications and connect with your team.</p>
@if(!empty($productNames))
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom:26px;background-color:#f6f5fe;border-radius:8px;"><tr><td style="padding:16px 18px;"><p style="margin:0 0 8px;font-size:11px;letter-spacing:1px;font-weight:bold;color:#74758a;">YOUR APPLICATION ACCESS</p><p style="margin:0;font-size:14px;line-height:1.8;color:#453a8d;">{{ implode(' · ', $productNames) }}</p></td></tr></table>
@endif
<table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td bgcolor="#7762ef" style="border-radius:7px;text-align:center;"><a href="{{ $acceptanceUrl }}" style="display:inline-block;padding:15px 26px;border:1px solid #7762ef;border-radius:7px;color:#ffffff;font-size:14px;font-weight:bold;text-decoration:none;">Accept invitation &rarr;</a></td></tr></table>
<p style="margin:18px 0 24px;font-size:12px;line-height:1.7;color:#72788b;">This invitation expires in seven days. Already have an account? Sign in with your existing account to accept.</p>
<div style="border-top:1px solid #e9eaf0;padding-top:20px;"><p style="margin:0 0 8px;font-size:12px;line-height:1.6;color:#72788b;">If the button doesn't work, copy this link into your browser:</p><p style="margin:0;font-size:11px;line-height:1.7;word-break:break-all;overflow-wrap:anywhere;"><a href="{{ $acceptanceUrl }}" style="color:#6554e8;text-decoration:underline;word-break:break-all;">{{ $acceptanceUrl }}</a></p></div>
</td></tr>
<tr><td style="padding:22px 12px;text-align:center;color:#858a99;font-size:12px;line-height:1.8;">Keep this invitation private; it grants access to your workspace.<br>If you weren't expecting it, you can safely ignore this email.<br><span style="color:#a0a4b0;">Judibas &middot; One workspace. Connected teams.</span></td></tr>
</table>
</td></tr></table>
</body></html>
