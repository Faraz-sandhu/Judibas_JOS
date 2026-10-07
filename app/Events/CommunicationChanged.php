<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class CommunicationChanged implements ShouldBroadcastNow
{
    public function __construct(public array $recipients, public string $kind = 'workspace', public ?int $conversationId = null) {}

    public function broadcastOn(): array
    {
        return array_map(fn ($id) => new PrivateChannel('communication.user.'.$id), array_unique([...$this->recipients, 'super']));
    }

    public function broadcastAs(): string
    {
        return 'communication.changed';
    }

    public function broadcastWith(): array
    {
        return ['kind' => $this->kind, 'conversation_id' => $this->conversationId];
    }
}
