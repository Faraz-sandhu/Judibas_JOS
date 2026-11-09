<?php

namespace App\Modules\Focus\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class FocusChanged implements ShouldBroadcastNow
{
    public function __construct(private string|array $owner) {}

    public function broadcastOn(): array
    {
        return array_map(fn ($owner) => new PrivateChannel('focus.'.str_replace(':', '.', $owner)), (array) $this->owner);
    }

    public function broadcastAs(): string
    {
        return 'focus.changed';
    }

    public function broadcastWith(): array
    {
        return ['changed' => true];
    }
}
