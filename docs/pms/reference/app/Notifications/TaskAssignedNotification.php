<?php

namespace App\Notifications;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;



class TaskAssignedNotification extends Notification implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels, Queueable;


    public $task;

    public $notifier_id;

    public function __construct($task, $notifier_id)
    {
        $this->task = $task;
        $this->notifier_id = $notifier_id;
    }

    public function via(object $notifiable): array
    {
        return ['broadcast', 'database'];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('task-notification.'.$this->notifier_id);
    }

    public function broadcastAs()
    {
        return 'TaskNotification';
    }

    public function broadcastWith()
    {
        return [
            'message' => "Task assigned to you",
            'task' => $this->task,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "Task assigned to you",
            'task' => $this->task,
        ];
    }
}
