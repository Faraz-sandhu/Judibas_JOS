<?php

namespace App\Http\Controllers;

use App\Models\MailSetting;
use App\Services\EmailDeliveryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class MailSettingController extends Controller
{
    use AuthorizesRequests;

    public function edit(EmailDeliveryService $emailDelivery): View
    {
        $this->authorize('mail-settings');
        $settings = MailSetting::current();
        return view('dashboard.settings.mail', [
            'settings' => $settings,
            'usageToday' => $emailDelivery->usageToday(),
            'availability' => $emailDelivery->availability('invitation'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('mail-settings');
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
            return back()->withErrors(['host' => 'SMTP host and From address are required while email delivery is enabled.'])->withInput();
        }
        if ($emailEnabled && ! $settings->password && blank($validated['password'] ?? null)) {
            return back()->withErrors(['password' => 'The SMTP password is required.'])->withInput();
        }
        if (blank($validated['password'] ?? null)) unset($validated['password']);
        $settings->update($validated);
        cache()->forget('mail.settings');
        $settings->refresh()->applyToConfig();
        Mail::purge('smtp');

        return back()->with('success', 'Email settings saved securely.');
    }

    public function test(Request $request): RedirectResponse
    {
        $this->authorize('mail-settings');
        $validated = $request->validate(['test_email' => ['required', 'email', 'max:255']]);
        $settings = MailSetting::current();
        if (! $settings->host || ! $settings->from_address || ! $settings->password) {
            return back()->with('error', 'Save the complete SMTP configuration before sending a test.');
        }

        try {
            $settings->applyToConfig();
            Mail::purge('smtp');
            Mail::send('emails.notification', [
                'eyebrow' => 'Email configuration',
                'badge' => 'Test successful',
                'title' => 'Your SMTP connection is working',
                'recipientName' => $request->user()->name,
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
            return back()->with('success', 'Test email sent to '.$validated['test_email'].'.');
        } catch (\Throwable $exception) {
            report($exception);
            return back()->with('error', 'Test email failed: '.$exception->getMessage());
        }
    }
}
