<?php
namespace App\Http\Controllers;
use App\Services\CommunicationAccess as Access;
use App\Events\CommunicationPresence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
class CommunicationPresenceController extends Controller
{
 private function effective(string $preference,array $tabs): string {
  $tabs=array_filter($tabs,fn($t)=>$t['expires']>time());
  if($preference==='offline'||!count($tabs))return 'offline';
  if($preference!=='online')return $preference;
  return in_array('online',array_column($tabs,'activity'))?'online':'away';
 }
 public function index(Request $r) {
  Access::authorize($r);$q=User::where('is_active',true);
  if(!Access::super($r))$q->whereIn('id',Access::eligible((int)$r->user()->id));
  $preferences=$q->pluck('communication_status','id');$states=[];
  foreach($preferences as $id=>$pref)$states[$id]=$this->effective($pref,Cache::get('communication.presence.tabs.'.$id,[]));
  return response()->json(['states'=>$states,'preferences'=>$preferences])->header('Cache-Control','private, no-store');
 }
 public function update(Request $r) {
  Access::mutable($r);abort_if(Access::super($r),403);
  $v=$r->validate(['state'=>'required_without:activity|in:online,away,busy,offline','activity'=>'required_without:state|in:online,away,offline','tab'=>'required_with:activity|uuid']);$u=$r->user();$id=(int)$u->id;
  $result=Cache::lock('communication.presence.lock.'.$id,5)->block(3,function()use($u,$id,$v){
   $u->refresh();
   if(isset($v['state'])){$u->communication_status=$v['state'];$u->save();}
   $tabs=Cache::get('communication.presence.tabs.'.$id,[]);$tabs=array_filter($tabs,fn($t)=>$t['expires']>time());
   if(isset($v['activity'])){if($v['activity']==='offline')unset($tabs[$v['tab']]);else $tabs[$v['tab']]=['activity'=>$v['activity'],'expires'=>time()+100];}
   Cache::put('communication.presence.tabs.'.$id,$tabs,120);
   return $this->effective($u->communication_status,$tabs);
  });
  try{event(new CommunicationPresence($id,$result,Access::eligible($id),$u->communication_status));}catch(\Throwable $e){}
  return response()->json(['state'=>$result,'preference'=>$u->communication_status]);
 }
}
