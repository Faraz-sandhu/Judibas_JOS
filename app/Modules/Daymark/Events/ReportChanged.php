<?php
namespace App\Modules\Daymark\Events;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
class ReportChanged implements ShouldBroadcastNow {
 public function __construct(public array $recipients,public int $reportId,public string $action){}
 public function broadcastOn():array{return array_map(fn($id)=>new PrivateChannel('daymark.user.'.$id),$this->recipients);}
 public function broadcastAs():string{return 'daymark.report.changed';}
 public function broadcastWith():array{return ['report_id'=>$this->reportId,'action'=>$this->action];}
}
