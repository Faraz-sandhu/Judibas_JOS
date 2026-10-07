<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class CommunicationCallSignal implements ShouldBroadcastNow
{
    public function __construct(public int $recipient, public array $payload) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('communication.user.'.$this->recipient)];
    }

    public function broadcastAs(): string
    {
        return 'communication.call';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
