<?php
namespace App\Modules\Canteen\Events;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
class OrderChanged implements ShouldBroadcastNow {
 public function __construct(public int $companyId, public int $orderId, public string $action='updated') {}
 public function broadcastOn(): array { return [new Channel('canteen.company.'.$this->companyId)]; }
 public function broadcastAs(): string { return 'canteen.order.changed'; }
 public function broadcastWith(): array { return ['company_id'=>$this->companyId,'order_id'=>$this->orderId,'action'=>$this->action]; }
}
