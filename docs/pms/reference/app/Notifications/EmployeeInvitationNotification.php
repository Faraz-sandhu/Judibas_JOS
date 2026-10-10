<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeeInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public Invitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You are invited to join the team')
            ->view('emails.notification', [
                'eyebrow' => 'Secure employee invitation',
                'badge' => 'Join the workspace',
                'title' => 'Your employee account is ready to activate',
                'recipientName' => $this->invitation->recipient_display,
                'bodyText' => $this->invitation->sender->name.' invited you to join the workspace. Choose your own password using the secure link below.',
                'details' => array_filter([
                    'Role' => $this->invitation->assignedRole?->role_name,
                    'Department' => $this->invitation->department?->dept_name,
                    'Invited by' => $this->invitation->sender->name,
                    'Expires' => $this->invitation->expires_at->format('M j, Y g:i A'),
                ]),
                'actionLabel' => 'Create my account',
                'actionUrl' => route('invitations.join', $this->invitation->token),
                'note' => 'This is a one-time link. No temporary password was created or sent by email.',
            ]);
    }
}
