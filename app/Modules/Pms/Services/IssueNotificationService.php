<?php

namespace App\Modules\Pms\Services;

use App\Modules\Pms\Models\Tasks;
use App\Modules\Pms\Models\User;
use App\Modules\Pms\Notifications\IssueActivityNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class IssueNotificationService
{
    public function __construct(private EmailDeliveryService $emailDelivery) {}

    public function assigned(Tasks $task, User $assignee): void
    {
        $actor = \App\Modules\Pms\Services\PmsAuth::user();
        $this->notify($task, collect([$assignee->id]), [
            'subject' => "You were assigned: {$task->title}",
            'message' => ($actor?->name ?? 'System').' assigned you task "'.$task->title.'".',
            'activity' => 'assigned',
            'type' => 'assignment',
        ]);
    }

    public function unassigned(Tasks $task, iterable $recipientIds): void
    {
        $actor = \App\Modules\Pms\Services\PmsAuth::user();
        $this->notify($task, collect($recipientIds), [
            'subject' => "You were unassigned: {$task->title}",
            'message' => ($actor?->name ?? 'System').' unassigned you from task "'.$task->title.'".',
            'activity' => 'unassigned',
            'type' => 'assignment',
            'url' => $task->project_id
                ? \App\Modules\Pms\Services\PmsUrls::workspace(['project' => $task->project_id, 'issue' => $task->id])
                : PmsUrls::workspace(['department' => $task->department_id, 'project' => 'department-backlog', 'issue' => $task->id]),
        ]);
    }

    public function commented(Tasks $task): void
    {
        $actor = \App\Modules\Pms\Services\PmsAuth::user();
        $this->notify($task, $this->issueRecipientIds($task), [
            'subject' => "New comment on: {$task->title}",
            'message' => ($actor?->name ?? 'System').' commented on task "'.$task->title.'".',
            'activity' => 'commented',
            'type' => 'comment',
        ]);
    }

    public function send(Tasks $task, string $activity, ?string $detail = null, array $additionalRecipientIds = []): void
    {
        $task->loadMissing(['project', 'users:id,name,email']);
        $actor = \App\Modules\Pms\Services\PmsAuth::user();
        $message = trim(($actor?->name ?? 'System')." {$activity} issue \"{$task->title}\".".($detail ? " {$detail}" : ''));

        $recipientIds = $this->issueRecipientIds($task)
            ->merge($additionalRecipientIds)
            ->unique()
            ->reject(fn ($id) => $actor && (int) $id === (int) $actor->id);

        $this->notify($task, $recipientIds, [
            'subject' => "Issue update: {$task->title}",
            'message' => $message,
            'activity' => $activity,
            'type' => 'issue_update',
        ]);
    }

    public function handoff(Tasks $destinationTask, Tasks $sourceTask, iterable $recipientIds, ?string $notes = null): void
    {
        $actor = \App\Modules\Pms\Services\PmsAuth::user();
        $sourceTask->loadMissing(['project', 'department']);
        $destinationTask->loadMissing(['project', 'department']);
        $detail = ($sourceTask->department?->dept_name ?? $sourceTask->project->name).' handed off “'.$sourceTask->title.'” to '.
            ($destinationTask->department?->dept_name ?? $destinationTask->project->name).'.'.($notes ? ' Notes: '.$notes : '');
        // Rebuild with plain ASCII quotes so notification text renders correctly
        // regardless of the source file or mail client's character encoding.
        $sourceName = $sourceTask->department?->dept_name ?? $sourceTask->project?->name ?? 'A department';
        $destinationName = $destinationTask->department?->dept_name ?? $destinationTask->project?->name ?? 'the receiving department';
        $detail = $sourceName.' handed off "'.$sourceTask->title.'" to '.$destinationName.'.'.($notes ? ' Notes: '.$notes : '');
        $destinationUrl = $destinationTask->project_id
            ? \App\Modules\Pms\Services\PmsUrls::workspace(['project' => $destinationTask->project_id, 'issue' => $destinationTask->id])
            : PmsUrls::workspace([
                'department' => $destinationTask->department_id,
                'project' => 'department-backlog',
                'issue' => $destinationTask->id,
            ]);
        $this->notify($destinationTask, collect($recipientIds), [
            'subject' => 'New department handoff: '.$destinationTask->title,
            'message' => ($actor?->name ?? 'System').' created a new backlog task for your department. '.$detail,
            'activity' => 'department handoff',
            'type' => 'task_handoff',
            'url' => $destinationUrl,
        ]);
    }

    private function issueRecipientIds(Tasks $task)
    {
        $task->loadMissing(['project', 'users:id,name,email']);
        $stakeholderIds = $task->project
            ? $task->project->users()->whereHas('roles', fn ($query) => $query->whereIn('role_key', ['project_manager', 'team_leader']))->pluck('users.id')
            : User::whereHas('departments', fn ($query) => $query->where('pms_departments.id', $task->department_id))
                ->whereHas('roles', fn ($query) => $query->where('role_key', 'team_leader'))->pluck('id');
        $adminIds = User::whereHas('roles', fn ($query) => $query->where('role_key', 'admin'))
            ->pluck('users.id');

        return $task->users->pluck('id')
            ->merge($stakeholderIds)
            ->merge($adminIds)
            ->unique();
    }

    private function notify(Tasks $task, $recipientIds, array $content): void
    {
        $task->loadMissing(['project', 'department']);
        $actor = \App\Modules\Pms\Services\PmsAuth::user();
        $recipientIds = collect($recipientIds)->unique()->reject(
            fn ($id) => $actor && (int) $id === (int) $actor->id
        );
        if ($recipientIds->isEmpty()) {
            return;
        }

        $data = $content + [
            'project_id' => $task->project_id,
            'project_name' => $task->project?->name ?? $task->department?->dept_name ?? 'Department backlog',
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'project_id' => $task->project_id,
                'status' => $task->status,
            ],
            'actor' => $actor ? ['id' => $actor->id, 'name' => $actor->name] : null,
            'url' => \App\Modules\Pms\Services\PmsUrls::workspace(['issue' => $task->id] + ['screen'=>'my-work']),
        ];

        $recipientIds = $recipientIds->map(fn ($id) => (int) $id)->values();
        $recipients = User::whereIn('id', $recipientIds)->get();

        // Persist the in-app notification first. This must never depend on a queue,
        // broadcaster, or mail server being available.
        \App\Modules\Pms\Services\PmsNotificationDelivery::send($recipients, new IssueActivityNotification($data, ['database']));

        // Broadcast and SMTP are external I/O and can be slow. Run them after the
        // HTTP response has been sent so task movement never waits for either
        // service. The broadcast message uses the sync connection so it does not
        // remain pending in the database queue when no worker is running.
        app()->terminating(function () use ($recipientIds, $data): void {
            $deferredRecipients = User::whereIn('id', $recipientIds)->get();

            try {
                \App\Modules\Pms\Services\PmsNotificationDelivery::send($deferredRecipients, new IssueActivityNotification($data, ['broadcast']));
            } catch (\Throwable $exception) {
                Log::warning('Issue notification broadcast failed.', ['exception' => $exception->getMessage()]);
            }

            foreach ($deferredRecipients->filter(fn (User $recipient) => filled($recipient->email)) as $recipient) {
                $this->emailDelivery->send($recipient, new IssueActivityNotification($data, ['mail']), 'notification');
            }
        });
    }
}
