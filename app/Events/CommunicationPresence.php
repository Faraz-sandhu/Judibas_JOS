<?php
namespace App\Events;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
class CommunicationPresence implements ShouldBroadcastNow
{
    public function __construct(public int $userId, public string $state, private array $recipients,public ?string $preference=null) {}
    public function broadcastOn(): array { return array_map(fn($id)=>new PrivateChannel('communication.user.'.$id),array_unique([...$this->recipients,'super'])); }
    public function broadcastAs(): string { return 'communication.presence'; }
    public function broadcastWith(): array { return ['user_id'=>$this->userId,'state'=>$this->state,'preference'=>$this->preference]; }
}
