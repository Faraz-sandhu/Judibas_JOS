<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class UserStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels, Queueable;
    /**
     * Create a new event instance.
     */
    public $user;
    public $status;
    public function __construct($user, $status)
    {
        $this->user = $user;
        $this->status = $status;
    }


    public function broadcastOn()
    {
        return new PresenceChannel('user-status');
    }

    public function broadcastAs()
    {
        return 'userStatus';
    }

    public function broadcastWith()
    {
        return [
            'status' => $this->status,
            'user' => $this->user,
            'last_seen_at' => $this->user->last_seen_at,
        ];
    }
}
