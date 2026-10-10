<?php

namespace App\Modules\Pms\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewUnseenMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $receiverId;
    public $unseenCount;

    public function __construct($receiverId, $unseenCount)
    {
        $this->receiverId = $receiverId;
        $this->unseenCount = $unseenCount;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('unseen-messages.' . $this->receiverId);
    }

    public function broadcastAs()
    {
        return 'new-unseen-message';
    }

    public function broadcastWith()
    {
        return [
            'unseen_count' => $this->unseenCount,
        ];
    }
}
