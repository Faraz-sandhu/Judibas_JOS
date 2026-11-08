<?php
namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\{ActivityNotifications as Activity,Permissions};
use App\Services\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Broadcast,DB};

class NotificationController
{
    public function index(Request $r)
    {
        $v=$r->validate(['company_id'=>'nullable|integer|min:1','before_id'=>'nullable|integer|min:1']);
        $q=Activity::query($r,isset($v['company_id'])?(int)$v['company_id']:null);
        $unread=(clone $q)->whereNull('r.read_at')->count();$latest=(int)(clone $q)->max('n.id');
        $rows=(clone $q)->when($v['before_id']??null,fn($q,$id)=>$q->where('n.id','<',$id))->orderByDesc('n.id')->limit(16)->get(['n.id','n.audit_id','n.company_id','c.name as company_name','n.actor_name','n.title','n.subject','n.section','n.created_at','r.read_at']);
        $more=$rows->count()>15;if($more)$rows->pop();
        return ['items'=>$rows->map(function($n)use($r){
            $url=\App\Modules\Accounting\Services\NotificationTarget::url($r,$n);
            unset($n->audit_id);
            return (array)$n+['url'=>$url];
        }),'unread_count'=>$unread,'latest_id'=>$latest,'has_more'=>$more];
    }
    public function read(Request $r)
    {
        $v=$r->validate(['company_id'=>'nullable|integer|min:1','id'=>'nullable|integer|min:1','through_id'=>'required_without:id|integer|min:0']);
        $q=Activity::query($r,isset($v['company_id'])?(int)$v['company_id']:null)->whereNull('r.read_at');
        if(isset($v['id'])){
            $exists=Activity::query($r,isset($v['company_id'])?(int)$v['company_id']:null)->where('n.id',$v['id'])->exists();abort_unless($exists,404);
            $q->where('n.id',$v['id']);
        }else $q->where('n.id','<=',$v['through_id']);
        $viewer=Activity::viewer($r);
        $q->select('n.id')->orderBy('n.id')->chunkById(500,function($rows)use($viewer){DB::table('accounting_notification_reads')->insertOrIgnore($rows->map(fn($row)=>['notification_id'=>$row->id,'viewer_key'=>$viewer,'read_at'=>now()])->all());},'n.id','id');
        return ['message'=>'Notifications marked as read.'];
    }
    public function config(Request $r)
    {
        $channels=Activity::channels($r);
        return response()->json(PlatformSettings::clientConfig()+['channels'=>$channels])->header('Cache-Control','private, no-store');
    }
    public function auth(Request $r)
    {
        $r->validate(['socket_id'=>['required','string','regex:/^\d+\.\d+$/'],'channel_name'=>'required|string|max:150']);
        $allowed=array_map(fn($channel)=>'private-'.$channel,Activity::channels($r));
        abort_unless(in_array($r->input('channel_name'),$allowed,true),403);
        abort_unless(in_array(config('broadcasting.default'),['reverb','pusher'],true),422,'Realtime is disabled.');
        return Broadcast::connection()->validAuthenticationResponse($r,true);
    }
}
