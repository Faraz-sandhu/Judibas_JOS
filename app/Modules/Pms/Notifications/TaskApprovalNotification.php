<?php

namespace App\Modules\Pms\Notifications;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class TaskApprovalNotification extends Notification implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels, Queueable;


    public $data;
    /**
     * Create a new notification instance.
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['broadcast', 'database'];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('pms.user.'.$this->data['user_id']);
    }

    public function broadcastAs()
    {
        return 'TaskApprovalNotification';
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->data['message'],
            'task' => $this->data['task'],
            'project_id' => $this->data['project_id'],
            'status' => $this->data['approval'],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->data['message'],
            'task' => $this->data['task'],
            'project_id' => $this->data['project_id'],
            'status' => $this->data['approval'],
        ];
    }
}
