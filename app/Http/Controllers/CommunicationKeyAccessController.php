<?php

namespace App\Http\Controllers;

use App\Events\CommunicationChanged;
use App\Services\CommunicationAccess as Access;
use App\Services\CommunicationProtection as Protection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommunicationKeyAccessController extends Controller
{
    private function conversation(Request $r, int $id, bool $super = false): object
    {
        Access::mutable($r);
        $c = Access::conversation($r, $id);
        abort_unless(Protection::participant($r, $c), 403);
        if ($super) abort_unless(Access::personal($r), 403);
        return $c;
    }

    private function response(array $value)
    {
        return response()->json($value)->header('Cache-Control','private, no-store');
    }

    private function notify(int $id): void
    {
        $recipients = DB::table('communication_members')->where('conversation_id',$id)->pluck('user_id')->all();
        try { event(new CommunicationChanged($recipients, 'workspace', $id)); }
        catch (\Throwable $e) { Log::warning('Protected chat access update could not be broadcast.', ['exception'=>get_class($e)]); }
    }

    public function register(Request $r)
    {
        Access::mutable($r);
        abort_if(Access::super($r) || !$r->user(), 403);
        abort_if(array_diff(array_keys($r->all()), ['id','public_key','mode','_token']),422,'Only a public key may be registered.');
        $v=$r->validate(['id'=>'required|uuid','public_key'=>'required|array','public_key.kty'=>'required|in:RSA','public_key.n'=>'required|string|max:600','public_key.e'=>'required|in:AQAB']);
        abort_if(array_diff(array_keys($r->input('public_key')), ['kty','n','e','alg','ext','key_ops']),422,'Private keys must never be uploaded.');
        $n=base64_decode(strtr($v['public_key']['n'],'-_','+/'),true);
        abort_unless($n!==false && strlen($n)===384 && (ord($n[0]) & 128),422,'A 3072-bit RSA public key is required.');
        $public=json_encode(['kty'=>'RSA','n'=>$v['public_key']['n'],'e'=>'AQAB','alg'=>'RSA-OAEP-256','ext'=>true,'key_ops'=>['encrypt']]);
        $user=(int)$r->user()->id;
        DB::transaction(function () use ($v,$public,$user) {
            DB::table('communication_protection_devices')->insertOrIgnore(['id'=>$v['id'],'user_id'=>$user,'public_key'=>$public,'created_at'=>now(),'updated_at'=>now()]);
            $old=DB::table('communication_protection_devices')->where('id',$v['id'])->lockForUpdate()->first();
            abort_unless($old && $old->user_id===$user && $old->public_key===$public,409,'A registered public key cannot be replaced.');
        });
        return $this->response(['id'=>$v['id']]);
    }

    public function show(Request $r, int $id)
    {
        $c=$this->conversation($r,$id);
        $key=json_decode($c->protection??'null',true)['key_id']??null;
        if (Access::personal($r)) {
            $members=DB::table('users')->where('is_active',true)->whereIn('id',DB::table('communication_members')->where('conversation_id',$id)->select('user_id'))->whereIn('id',DB::table('user_product_access')->where('product_slug','communication')->select('user_id'))->pluck('id');
            $devices=DB::table('communication_protection_devices')->whereIn('user_id',$members)->orderByDesc('created_at')->get(['id','user_id','public_key']);
            foreach ($devices as $device) $device->public_key=json_decode($device->public_key);
            $requests=DB::table('communication_key_requests as r')->join('communication_protection_devices as d','d.id','=','r.device_id')->join('users as u','u.id','=','d.user_id')->where('r.conversation_id',$id)->where('r.status','pending')->whereIn('d.user_id',$members)->orderBy('r.id')->get(['r.id','r.device_id','u.name','r.created_at']);
            $recovery=DB::table('communication_protection_recovery')->where('conversation_id',$id)->first();
            return $this->response(['devices'=>$devices,'requests'=>$requests,'has_delivery'=>DB::table('communication_key_deliveries')->where('conversation_id',$id)->where('key_id',$key)->exists(),'recovery'=>$recovery?json_decode($recovery->envelope):null]);
        }
        $v=$r->validate(['device_id'=>'required|uuid']);
        $device=DB::table('communication_protection_devices')->where('id',$v['device_id'])->where('user_id',$r->user()->id)->first();
        abort_unless($device,403);
        $delivery=DB::table('communication_key_deliveries')->where('conversation_id',$id)->where('device_id',$device->id)->where('key_id',$key)->whereNull('acknowledged_at')->whereNotNull('envelope')->latest('created_at')->first(['id','key_id','envelope']);
        if ($delivery) $delivery->envelope=json_decode($delivery->envelope);
        $pending=DB::table('communication_key_requests')->where('conversation_id',$id)->where('device_id',$device->id)->where('status','pending')->exists();
        return $this->response(['delivery'=>$delivery,'requested'=>$pending,'has_history'=>DB::table('communication_key_deliveries')->where('conversation_id',$id)->where('device_id',$device->id)->where('key_id',$key)->exists()]);
    }

    public function requestAccess(Request $r, int $id)
    {
        $c=$this->conversation($r,$id);
        abort_if(Access::super($r),403);
        abort_unless($c->protection,422,'Protection has not been enabled for this chat.');
        $v=$r->validate(['device_id'=>'required|uuid']);
        abort_unless(DB::table('communication_protection_devices')->where('id',$v['device_id'])->where('user_id',$r->user()->id)->exists(),403);
        DB::transaction(function () use ($id,$v) {
            DB::table('communication_conversations')->where('id',$id)->lockForUpdate()->first();
            $old=DB::table('communication_key_requests')->where('conversation_id',$id)->where('device_id',$v['device_id'])->first();
            if ($old && $old->status==='pending') return;
            DB::table('communication_key_requests')->updateOrInsert(['conversation_id'=>$id,'device_id'=>$v['device_id']],['status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
        });
        $this->notify($id);
        return $this->response(['requested'=>true]);
    }

    public function deliver(Request $r, int $id)
    {
        $c=$this->conversation($r,$id,true);
        abort_if(array_diff(array_keys($r->all()),['id','device_id','key_id','ciphertext','mode','_token']),422,'Only an encrypted key envelope may be sent.');
        $v=$r->validate(['id'=>'required|uuid','device_id'=>'required|uuid','key_id'=>'required|uuid','ciphertext'=>'required|string|max:600']);
        $raw=base64_decode($v['ciphertext'],true);
        abort_unless($raw!==false && strlen($raw)===384,422,'Invalid encrypted key envelope.');
        DB::transaction(function () use ($id,$v) {
            $c=DB::table('communication_conversations')->where('id',$id)->lockForUpdate()->first();
            abort_unless((json_decode($c->protection??'null',true)['key_id']??null)===$v['key_id'],422,'Chat key does not match.');
            $device=DB::table('communication_protection_devices')->where('id',$v['device_id'])->first();
            abort_unless($device && DB::table('communication_members')->where('conversation_id',$id)->where('user_id',$device->user_id)->exists(),403);
            abort_unless(DB::table('users')->where('id',$device->user_id)->where('is_active',true)->whereIn('id',DB::table('user_product_access')->where('product_slug','communication')->select('user_id'))->exists(),403);
            $pending=DB::table('communication_key_requests')->where('conversation_id',$id)->where('device_id',$device->id)->where('status','pending')->exists();
            $existing=DB::table('communication_key_deliveries')->where('id',$v['id'])->first();
            if ($existing) {
                abort_unless($existing->conversation_id===$id && $existing->device_id===$device->id && $existing->key_id===$v['key_id'],409);
                return;
            }
            abort_if(DB::table('communication_key_deliveries')->where('conversation_id',$id)->where('key_id',$v['key_id'])->exists() && !$pending,409,'The recipient must request access before a key is sent again.');
            DB::table('communication_key_deliveries')->where('conversation_id',$id)->where('device_id',$device->id)->where('key_id',$v['key_id'])->whereNull('acknowledged_at')->update(['envelope'=>null,'acknowledged_at'=>now(),'updated_at'=>now()]);
            DB::table('communication_key_deliveries')->insert(['id'=>$v['id'],'conversation_id'=>$id,'device_id'=>$device->id,'key_id'=>$v['key_id'],'envelope'=>json_encode(['ciphertext'=>$v['ciphertext']]),'created_at'=>now(),'updated_at'=>now()]);
            DB::table('communication_key_requests')->where('conversation_id',$id)->where('device_id',$device->id)->update(['status'=>'fulfilled','updated_at'=>now()]);
        });
        $this->notify($id);
        return $this->response(['delivered'=>true]);
    }

    public function acknowledge(Request $r, int $id)
    {
        $this->conversation($r,$id);
        abort_if(Access::super($r),403);
        $v=$r->validate(['delivery_id'=>'required|uuid','device_id'=>'required|uuid']);
        abort_unless(DB::table('communication_protection_devices')->where('id',$v['device_id'])->where('user_id',$r->user()->id)->exists(),403);
        $q=DB::table('communication_key_deliveries')->where('id',$v['delivery_id'])->where('device_id',$v['device_id'])->where('conversation_id',$id);
        abort_unless((clone $q)->exists(),404);
        $q->update(['envelope'=>null,'acknowledged_at'=>now(),'updated_at'=>now()]);
        return $this->response(['acknowledged'=>true]);
    }

    public function recovery(Request $r, int $id)
    {
        $c=$this->conversation($r,$id,true);
        abort_if(array_diff(array_keys($r->all()),['key_id','iv','ciphertext','mode','_token']),422,'Only an encrypted recovery envelope may be saved.');
        $v=$r->validate(['key_id'=>'required|uuid','iv'=>'required|string|max:32','ciphertext'=>'required|string|max:100']);
        Protection::validateEnvelope($v);
        abort_unless(strlen(base64_decode($v['ciphertext'],true))===48,422);
        DB::transaction(function () use ($id,$v) {
            $c=DB::table('communication_conversations')->where('id',$id)->lockForUpdate()->first();
            abort_unless((json_decode($c->protection??'null',true)['key_id']??null)===$v['key_id'],422);
            $old=DB::table('communication_protection_recovery')->where('conversation_id',$id)->first();
            if ($old) {
                abort_unless(json_decode($old->envelope,true)===$v,409,'Recovery is already configured. Use your saved recovery key.');
                return;
            }
            DB::table('communication_protection_recovery')->insert(['conversation_id'=>$id,'key_id'=>$v['key_id'],'envelope'=>json_encode($v),'created_at'=>now(),'updated_at'=>now()]);
        });
        return $this->response(['saved'=>true]);
    }
}
