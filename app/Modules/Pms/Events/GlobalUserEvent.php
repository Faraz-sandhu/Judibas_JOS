<?php

namespace App\Modules\Pms\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GlobalUserEvent
{
    use Dispatchable, InteractsWithSockets, Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function via(object $notifiable): array
    {
        return ['broadcast', 'database'];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('pms.global-update.'.$this->data['user_id']);
    }

    public function broadcastAs()
    {
        return 'globalUpdate';
    }

    public function broadcastWith()
    {
        return [
            'status' => $this->data['status'] ?? null,
            'project_id' => $this->data['project_id'] ?? null,
            'task_id' => $this->data['task_id'] ?? null,
            'sub_task_id' => $this->data['sub_task_id'] ?? null,
            'task' => $this->data['task'] ?? null,
            'user_id' => $this->data['user_id'] ?? null,
            'approval' => $this->data['approval'] ?? null,
            'message' => $this->data['message'] ?? null,
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'status' => $this->data['status'] ?? null,
            'project_id' => $this->data['project_id'] ?? null,
            'task_id' => $this->data['task_id'] ?? null,
            'sub_task_id' => $this->data['sub_task_id'] ?? null,
            'task' => $this->data['task'] ?? null,
            'user_id' => $this->data['user_id'] ?? null,
            'approval' => $this->data['approval'] ?? null,
            'message' => $this->data['message'] ?? null,
        ];
    }
}
