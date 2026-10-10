<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Carbon\Carbon;

class TaskReminderNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public $data;

    public $user;

    public function __construct($task, $user)
    {
        $this->data = [
            'task_id' => $task->id,
            'title' => $task->title,
            'project_id' => $task->project_id,
            'due_date' => Carbon::parse($task->due_date)->format('Y-m-d H:i:s'),
            'remaining_time' => Carbon::now()->diffForHumans($task->due_date, true),
            'message' => "Reminder: Task is due soon. Only {$this->remainingTime($task)} remaining.",
        ];

        $this->user = $user;
    }

    protected function remainingTime($task)
    {
        return Carbon::now()->diffForHumans($task->due_date, true);
    }

    public function via($notifiable)
    {
        return ['broadcast', 'database'];
    }

    public function broadcastOn()
    {
        return new PrivateChannel('task-reminder-notification.' . $this->user);
    }

    public function broadcastAs()
    {
        return 'TaskReminderNotification';
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->data['message'],
            'task_id' => $this->data['task_id'],
            'title' => $this->data['title'],
            'project_id' => $this->data['project_id'],
            'due_date' => $this->data['due_date'],
            'remaining_time' => $this->data['remaining_time'],
        ];
    }

    public function toArray($notifiable)
    {
        return [
            'message' => 'Reminder: Task is due soon',
            'task'=>$this->data
            
        ];
    }
}