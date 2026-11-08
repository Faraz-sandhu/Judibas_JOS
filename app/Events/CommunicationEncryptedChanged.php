<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class CommunicationEncryptedChanged implements ShouldBroadcastNow
{
    public function __construct(private int $peer, private string $thread, private ?string $signal = null) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('communication.user.super'), new PrivateChannel('communication.user.'.$this->peer)];
    }

    public function broadcastAs(): string
    {
        return 'communication.encrypted';
    }

    public function broadcastWith(): array
    {
        return ['thread_id' => $this->thread] + ($this->signal ? ['signal_id' => $this->signal] : []);
    }
}
