<?php
namespace App\Modules\Pms\Services;
use Illuminate\Support\Facades\Notification;
class PmsNotificationDelivery {
 public static function send($recipients,$notification):void {
  foreach($recipients as $recipient){$channels=$notification->via($recipient);Notification::sendNow($recipient,$notification,array_values(array_diff($channels,['broadcast'])));if(in_array('broadcast',$channels))PmsRealtime::publish(new \App\Modules\Pms\Events\PmsNotificationBroadcast($recipient,$notification));}
 }
}
