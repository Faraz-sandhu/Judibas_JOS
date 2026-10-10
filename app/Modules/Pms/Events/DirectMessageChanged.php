<?php
namespace App\Modules\Pms\Events;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
class DirectMessageChanged {
 public function __construct(public int $from,public int $to){}
 public function broadcastOn():array{return [new PrivateChannel('pms.direct.'.$this->from),new PrivateChannel('pms.direct.'.$this->to)];}
 public function broadcastAs():string{return 'pms.direct.changed';}
 public function broadcastWith():array{return ['from_id'=>$this->from,'to_id'=>$this->to];}
}
