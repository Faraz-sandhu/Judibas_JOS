<?php
namespace App\Modules\Pms\Events;
use Illuminate\Broadcasting\PrivateChannel;
class PmsNotificationBroadcast {
 public function __construct(public $recipient,public $notification){}
 public function broadcastOn(){return method_exists($this->notification,'broadcastOn')?$this->notification->broadcastOn():[new PrivateChannel('pms.user.'.$this->recipient->id)];}
 public function broadcastAs():string{return method_exists($this->notification,'broadcastAs')?$this->notification->broadcastAs():'Illuminate\\Notifications\\Events\\BroadcastNotificationCreated';}
 public function broadcastWith():array{$n=$this->notification;$data=method_exists($n,'broadcastWith')?$n->broadcastWith(): (method_exists($n,'toBroadcast')?$n->toBroadcast($this->recipient)->data:$n->toArray($this->recipient));return $data+['id'=>$n->id,'type'=>get_class($n)];}
}
