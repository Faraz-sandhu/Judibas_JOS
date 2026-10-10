<?php

namespace App\Modules\Pms\Notifications;

use App\Modules\Pms\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public Invitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $task = $this->invitation->invitable;

        return (new MailMessage)
            ->subject('You have been invited to a task')
            ->view('pms.emails.notification', [
                'eyebrow' => 'Secure invitation',
                'badge' => 'You are invited',
                'title' => 'Join a task in '.$task->project->name,
                'recipientName' => $this->invitation->recipient_display,
                'bodyText' => $this->invitation->sender->name.' invited you to collaborate on a task.',
                'details' => [
                    'Project' => $task->project->name,
                    'Task' => $task->title,
                    'Invited by' => $this->invitation->sender->name,
                    'Expires' => $this->invitation->expires_at->format('M j, Y g:i A'),
                ],
                'actionLabel' => 'Accept invitation',
                'actionUrl' => url('/pms/join/'.$this->invitation->token),
                'note' => 'For your security, no password is sent by email. You will create your own password after opening this one-time link.',
            ]);
    }
}
