<?php

namespace App\Services;

use App\Models\EmailDelivery;
use App\Models\Invitation;
use App\Models\MailSetting;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class EmailDeliveryService
{
    public function usageToday(): int
    {
        return EmailDelivery::query()->whereNotNull('sent_at')->whereDate('sent_at', today())->count();
    }

    public function availability(string $type): array
    {
        $settings = MailSetting::current();
        if (! $settings->host || ! $settings->from_address || ! $settings->password) {
            return ['allowed' => false, 'status' => 'not_configured', 'message' => 'SMTP is not configured.'];
        }
        if ($type === 'invitation' && ! $settings->invitation_emails_enabled) {
            return ['allowed' => false, 'status' => 'email_disabled', 'message' => 'Invitation emails are disabled.'];
        }
        if ($type === 'notification' && ! $settings->notification_emails_enabled) {
            return ['allowed' => false, 'status' => 'email_disabled', 'message' => 'Notification emails are disabled.'];
        }
        if (cache()->get('mail.provider_quota_blocked.'.today()->toDateString())) {
            return ['allowed' => false, 'status' => 'quota_reached', 'message' => 'The email provider quota has been reached.'];
        }
        if ($settings->daily_limit > 0 && $this->usageToday() >= $settings->daily_limit) {
            return ['allowed' => false, 'status' => 'quota_reached', 'message' => 'The configured daily email limit has been reached.'];
        }

        return ['allowed' => true, 'status' => 'ready', 'message' => 'Email delivery is available.'];
    }

    public function send(object $recipient, Notification $notification, string $type, ?Invitation $invitation = null): array
    {
        $email = method_exists($recipient, 'routeNotificationForMail')
            ? $recipient->routeNotificationForMail($notification)
            : ($recipient->email ?? null);
        $email = is_array($email) ? array_key_first($email) : $email;
        $availability = $this->availability($type);

        if (! $availability['allowed']) {
            $this->record($type, (string) $email, $availability['status'], $availability['message'], $invitation);
            $this->updateInvitation($invitation, $availability['status'], $availability['message']);
            return $availability;
        }

        $settings = MailSetting::current();
        try {
            $settings->applyToConfig();
            Mail::purge('smtp');
            NotificationFacade::sendNow($recipient, $notification, ['mail']);
            $this->record($type, (string) $email, 'sent', null, $invitation, now());
            $this->updateInvitation($invitation, 'sent');
            return ['allowed' => true, 'status' => 'sent', 'message' => 'Email sent successfully.'];
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
            if (preg_match('/quota|daily limit|rate limit|too many|exceeded/i', $error)) {
                cache()->put('mail.provider_quota_blocked.'.today()->toDateString(), true, now()->endOfDay());
            }
            $this->record($type, (string) $email, 'failed', $error, $invitation);
            $this->updateInvitation($invitation, 'failed', $error);
            Log::warning('Email delivery failed.', ['type' => $type, 'recipient' => $email, 'exception' => $error]);
            return ['allowed' => false, 'status' => 'failed', 'message' => 'The email could not be delivered.'];
        }
    }

    private function record(string $type, string $recipient, string $status, ?string $error, ?Invitation $invitation, $sentAt = null): void
    {
        EmailDelivery::create([
            'type' => $type,
            'recipient' => $recipient,
            'invitation_id' => $invitation?->id,
            'status' => $status,
            'error' => $error ? mb_substr($error, 0, 4000) : null,
            'sent_at' => $sentAt,
        ]);
    }

    private function updateInvitation(?Invitation $invitation, string $status, ?string $error = null): void
    {
        if (! $invitation) return;
        $invitation->update([
            'delivery_status' => $status,
            'emailed_at' => $status === 'sent' ? now() : $invitation->emailed_at,
            'email_error' => $error ? mb_substr($error, 0, 4000) : null,
        ]);
    }
}
