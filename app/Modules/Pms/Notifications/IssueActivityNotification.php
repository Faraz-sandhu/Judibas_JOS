<?php

namespace App\Modules\Pms\Notifications;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IssueActivityNotification extends Notification
{
    public function __construct(public array $data, private array $channels = ['database', 'broadcast', 'mail'])
    {}

    public function via(object $notifiable): array
    {
        return array_values(array_filter(
            $this->channels,
            fn (string $channel) => $channel !== 'mail' || filled($notifiable->email)
        ));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $activity = $this->data['activity'] ?? 'updated';
        $labels = [
            'assigned' => ['Task assignment', 'You have a new task', 'Open task'],
            'unassigned' => ['Assignment changed', 'You were removed from a task', 'View task'],
            'commented' => ['New comment', 'A task has a new comment', 'Read comment'],
        ];
        [$badge, $title, $actionLabel] = $labels[$activity] ?? ['Task update', $this->data['subject'], 'View task'];

        return (new MailMessage)
            ->subject($this->data['subject'])
            ->view('pms.emails.notification', [
                'eyebrow' => 'Workspace notification',
                'badge' => $badge,
                'title' => $title,
                'recipientName' => $notifiable->name,
                'bodyText' => $this->data['message'],
                'details' => [
                    'Project' => $this->data['project_name'],
                    'Task' => $this->data['task']['title'],
                    'Status' => ucwords(str_replace('_', ' ', $this->data['task']['status'] ?? 'pending')),
                ],
                'actionLabel' => $actionLabel,
                'actionUrl' => $this->data['url'],
                'note' => $activity === 'unassigned'
                    ? 'This task will no longer appear in My Work unless it is assigned to you again.'
                    : 'Open the task to review its latest details and activity.',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return $this->data;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        // BroadcastNotificationCreated implements ShouldBroadcast and would
        // otherwise be stored in the database queue. Use the sync connection
        // so realtime delivery does not depend on a queue worker being active.
        return (new BroadcastMessage($this->data))->onConnection('sync');
    }
}
