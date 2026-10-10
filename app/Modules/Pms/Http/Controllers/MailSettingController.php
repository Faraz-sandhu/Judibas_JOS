<?php

namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Services\PmsAccess;

use App\Modules\Pms\Models\MailSetting;
use App\Modules\Pms\Services\EmailDeliveryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class MailSettingController extends Controller
{
    use AuthorizesRequests;

    public function edit(EmailDeliveryService $emailDelivery)
    {
        PmsAccess::requirePermission(request(), 'mail-settings');
        $settings = MailSetting::current();
        return view('dashboard.settings.mail', [
            'settings' => $settings,
            'usageToday' => $emailDelivery->usageToday(),
            'availability' => $emailDelivery->availability('invitation'),
        ]);
    }

    public function update(Request $request)
    {
        PmsAccess::requirePermission(request(), 'mail-settings');
        $validated = $request->validate([
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'scheme' => ['required', 'in:smtp,smtps'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:2000'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'notification_emails_enabled' => ['required', 'boolean'],
            'invitation_emails_enabled' => ['required', 'boolean'],
            'daily_limit' => ['required', 'integer', 'between:0,100000'],
            'warning_threshold' => ['required', 'integer', 'min:0', 'lte:daily_limit'],
        ]);

        $settings = MailSetting::current();
        $emailEnabled = (bool) $validated['notification_emails_enabled'] || (bool) $validated['invitation_emails_enabled'];
        if ($emailEnabled && (blank($validated['host']) || blank($validated['from_address']))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['host' => 'SMTP host and From address are required while email delivery is enabled.']);
        }
        if ($emailEnabled && ! $settings->password && blank($validated['password'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['password' => 'The SMTP password is required.']);
        }
        if (blank($validated['password'] ?? null)) unset($validated['password']);
        $settings->update($validated);
        cache()->forget('pms.mail.settings');
        $settings->refresh()->applyToConfig();
        Mail::purge('smtp');

        return response()->json(['message' => 'Email settings saved securely.']);
    }

    public function test(Request $request)
    {
        PmsAccess::requirePermission(request(), 'mail-settings');
        $validated = $request->validate(['test_email' => ['required', 'email', 'max:255']]);
        $settings = MailSetting::current();
        if (! $settings->host || ! $settings->from_address || ! $settings->password) {
            return response()->json(['message' => 'Save the complete SMTP configuration before sending a test.'], 422);
        }

        $original=config('mail');
        try {
            $settings->applyToConfig();
            Mail::purge('smtp');
            Mail::send('pms.emails.notification', [
                'eyebrow' => 'Email configuration',
                'badge' => 'Test successful',
                'title' => 'Your SMTP connection is working',
                'recipientName' => \App\Modules\Pms\Services\PmsAuth::user()->name,
                'bodyText' => 'This message confirms that your saved SMTP credentials can deliver application email successfully.',
                'details' => [
                    'SMTP server' => $settings->host.':'.$settings->port,
                    'Encryption' => strtoupper($settings->scheme ?: 'smtp'),
                    'From address' => $settings->from_address,
                ],
                'actionLabel' => 'Return to email settings',
                'actionUrl' => route('settings.mail.edit'),
                'note' => 'Task invitations and task activity notifications will use this same email configuration.',
            ], function ($message) use ($validated) {
                $message->to($validated['test_email'])->subject('PMS email configuration test');
            });
            return response()->json(['message' => 'Test email sent to '.$validated['test_email'].'.']);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'Test email failed: '.$exception->getMessage()], 422);
        } finally {config(['mail'=>$original]);Mail::purge('smtp');}
    }
}
