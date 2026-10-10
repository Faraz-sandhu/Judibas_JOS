<?php

namespace App\Modules\Pms\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Modules\Pms\Models\ProjectChat;


class ProjectChatMessageSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $chat;
    /**
     * Create a new event instance.
     */


    public function __construct(ProjectChat $chat)
    {
        $this->chat = $chat->load('sender', 'replies.sender');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn()
    {
        return new PrivateChannel('pms.project.chat.' . $this->chat->project_id);
    }

    public function broadcastWith()
    {
        return [
            'chat' => $this->chat
        ];
    }

    public function broadcastAs()
    {
        return 'ProjectChatMessageSent';
    }
}
