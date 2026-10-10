<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class StatusUpdateEvent extends Notification implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels, Queueable;

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
        return new PrivateChannel('status-update.'.$this->data['user_id']);
    }

    public function broadcastAs()
    {
        return 'StatusUpdate';
    }

    public function broadcastWith()
    {
        return [
            'message' => "Status updated",
            'status' => $this->data['status'] ?? null,
            'project_id' => $this->data['project_id'] ?? null,
            'task_id' => $this->data['task_id'] ?? null,
            'sub_task_id' => $this->data['sub_task_id'] ?? null,
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
            'message' => "Status updated",
            'status' => $this->data['status'] ?? null,
            'project_id' => $this->data['project_id'] ?? null,
            'task_id' => $this->data['task_id'] ?? null,
            'sub_task_id' => $this->data['sub_task_id'] ?? null,
        ];
    }

}
