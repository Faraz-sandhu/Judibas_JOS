<?php
namespace App\Modules\Pms\Services;
use App\Modules\Pms\Models\RealtimeSetting;
use Illuminate\Support\Facades\Event;
use Pusher\Pusher;
class PmsRealtime {
 public static function clientConfig():array {
  $s=RealtimeSetting::current();$legacy=!$s->useReverb();$c=config('broadcasting.connections.reverb');
  return ['enabled'=>$s->enabled&&(!$legacy||$s->isComplete()),'provider'=>$legacy?'pusher':'reverb','key'=>$legacy?$s->effectiveKey():$c['key'],'cluster'=>$legacy?$s->effectiveCluster():'mt1','host'=>env('VITE_REVERB_HOST')?:request()->getHost(),'port'=>$c['options']['port'],'scheme'=>$c['options']['scheme']];
 }
 private static function client():?Pusher {
  $s=RealtimeSetting::current();if(!$s->enabled)return null;
  if(!$s->useReverb()){if(!$s->isComplete())return null;return new Pusher($s->effectiveKey(),$s->effectiveSecret(),$s->effectiveAppId(),['cluster'=>$s->effectiveCluster(),'useTLS'=>true,'timeout'=>2]);}
  $c=config('broadcasting.connections.reverb');return new Pusher($c['key'],$c['secret'],$c['app_id'],$c['options']+['timeout'=>2]);
 }
 public static function testConnection():void {$client=self::client();abort_unless($client,422,'Enable realtime and save complete settings first.');$id=PmsAuth::id()??'super';$client->trigger('private-pms.global-update.'.$id,'globalUpdate',['activity'=>'test']);}
 public static function publish(object $event):void {Event::dispatch($event);}
 public static function deliver(object $event):void {
  $client=self::client();if(!$client)return;try{$channels=$event->broadcastOn();$channels=is_array($channels)?$channels:[$channels];$client->trigger(array_map(fn($channel)=>(string)$channel,$channels),$event->broadcastAs(),$event->broadcastWith());}catch(\Throwable $e){report($e);}
 }
 public static function authorize(\Illuminate\Http\Request $request){
  $request->validate(['socket_id'=>'required|string|max:100','channel_name'=>'required|string|max:200']);PmsAccess::authorize($request);
  $channel=$request->channel_name;$actor=PmsAuth::id();$super=$request->session()->get('judibas_admin');$allowed=false;
  if(preg_match('/^private-pms\\.project\\.chat\\.(\\d+)$/',$channel,$m)){app(\App\Modules\Pms\Http\Controllers\ProjectChatController::class)->ensureProjectAccess(\App\Modules\Pms\Models\Projects::findOrFail($m[1]));$allowed=true;}
  elseif(preg_match('/^private-pms\\.global-update\\.(super|\\d+)$/',$channel,$m))$allowed=$super?$m[1]==='super':(string)$actor===$m[1];
  elseif(preg_match('/^private-pms\\.user\\.(\\d+)$/',$channel,$m))$allowed=$actor!==null&&(string)$actor===$m[1];
  elseif(preg_match('/^private-pms\\.direct\\.(\\d+)$/',$channel,$m)){PmsAccess::requirePermission($request,'message-access');$allowed=$super?$m[1]==='0':(string)$actor===$m[1];}
  abort_unless($allowed,403);$client=self::client();abort_unless($client,422,'PMS realtime is disabled or incomplete.');return response($client->authorizeChannel($channel,$request->socket_id),200,['Content-Type'=>'application/json']);
 }
}
