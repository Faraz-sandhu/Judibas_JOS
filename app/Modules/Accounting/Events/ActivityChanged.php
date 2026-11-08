<?php
namespace App\Modules\Accounting\Events;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ActivityChanged implements ShouldBroadcastNow
{
    public function __construct(public int $companyId,public int $notificationId){}
    public function broadcastOn():array {return [new PrivateChannel('accounting.activity.admin'),new PrivateChannel('accounting.activity.company.'.$this->companyId)];}
    public function broadcastAs():string {return 'accounting.activity';}
    // Only invalidate the feed; financial details are fetched through the scoped API.
    public function broadcastWith():array {return ['company_id'=>$this->companyId,'notification_id'=>$this->notificationId];}
}
