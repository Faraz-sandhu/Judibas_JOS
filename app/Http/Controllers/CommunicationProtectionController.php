<?php

namespace App\Http\Controllers;

use App\Services\CommunicationAccess as Access;
use App\Services\CommunicationProtection as Protection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunicationProtectionController extends Controller
{
    public function rotate(Request $r, int $id)
    {
        Access::mutable($r);
        $c=Access::conversation($r,$id);
        abort_unless(Access::personal($r) && Protection::participant($r,$c),403);
        $v=$r->validate(['expected_key_id'=>'required|uuid','confirmed'=>'required|accepted','key_id'=>'required|uuid','iv'=>'required|string|max:32','ciphertext'=>'required|string|max:1000']);
        Protection::validateEnvelope($v);
        $next=['key_id'=>$v['key_id'],'iv'=>$v['iv'],'ciphertext'=>$v['ciphertext'],'future'=>true];
        DB::transaction(function () use ($id,$v,$next) {
            $c=DB::table('communication_conversations')->where('id',$id)->lockForUpdate()->first();
            $old=json_decode($c->protection??'null',true);
            abort_unless($old && $old['key_id']===$v['expected_key_id'],409,'Chat protection changed. Reopen the chat before creating a new key.');
            abort_if($old['key_id']===$v['key_id'] || DB::table('communication_protection_history')->where('conversation_id',$id)->where('key_id',$v['key_id'])->exists(),422,'Use a new key identifier.');
            $recovery=DB::table('communication_protection_recovery')->where('conversation_id',$id)->value('envelope');
            DB::table('communication_protection_history')->insert(['conversation_id'=>$id,'key_id'=>$old['key_id'],'protection'=>$c->protection,'recovery'=>$recovery,'created_at'=>now()]);
            DB::table('communication_protection_recovery')->where('conversation_id',$id)->delete();
            DB::table('communication_conversations')->where('id',$id)->update(['protection'=>json_encode($next),'updated_at'=>now()]);
        });
        return response()->json(['protection'=>$next])->header('Cache-Control','private, no-store');
    }

    public function history(Request $r, int $id, string $key)
    {
        $c=Access::conversation($r,$id);
        abort_unless(Protection::participant($r,$c),403);
        $old=DB::table('communication_protection_history')->where('conversation_id',$id)->where('key_id',$key)->first();
        abort_unless($old,404);
        $delivery=null;
        if (!Access::super($r) && $r->filled('device_id')) {
            $device=$r->validate(['device_id'=>'required|uuid'])['device_id'];
            abort_unless(DB::table('communication_protection_devices')->where('id',$device)->where('user_id',$r->user()->id)->exists(),403);
            $delivery=DB::table('communication_key_deliveries')->where('conversation_id',$id)->where('key_id',$key)->where('device_id',$device)->whereNull('acknowledged_at')->whereNotNull('envelope')->latest('created_at')->first(['id','key_id','envelope']);
            if ($delivery) $delivery->envelope=json_decode($delivery->envelope);
        }
        return response()->json(['protection'=>json_decode($old->protection),'recovery'=>Access::personal($r)&&$old->recovery?json_decode($old->recovery):null,'delivery'=>$delivery])->header('Cache-Control','private, no-store');
    }

    public function send(Request $r, int $id)
    {
        Access::mutable($r);
        $c = Access::conversation($r, $id);
        abort_unless(Protection::participant($r, $c), 403);
        $v = $r->validate(['protected_payload'=>'required|json|max:140000', 'protected_uuid'=>'required|uuid|unique:communication_messages,protected_uuid', 'attachment'=>'nullable|file|max:10241', 'reply_to'=>'nullable|integer']);
        abort_if($r->filled('body'), 422, 'Protected messages must not include plaintext.');
        $payload = json_decode($v['protected_payload'], true);
        validator($payload, ['key_id'=>'required|uuid','iv'=>'required|string|max:32','ciphertext'=>'required|string|max:140000','file_iv'=>'nullable|string|max:32'])->validate();
        Protection::validateEnvelope($payload);
        $config = json_decode($c->protection ?? 'null', true);
        abort_unless($config && $config['key_id'] === $payload['key_id'], 422, 'Chat protection key does not match.');
        if ($r->hasFile('attachment')) {
            abort_unless(strlen(base64_decode($payload['file_iv'] ?? '', true) ?: '') === 12, 422, 'Invalid file encryption envelope.');
        }
        if (!empty($v['reply_to'])) abort_unless(DB::table('communication_messages')->where('id',$v['reply_to'])->where('conversation_id',$id)->whereNull('deleted_at')->exists(),422);
        // Only ciphertext is accepted here. The original filename and MIME type live inside the encrypted payload.
        $path = $r->hasFile('attachment') ? $r->file('attachment')->store('communication-protected', 'local') : null;
        abort_if($r->hasFile('attachment') && !$path, 422, 'Unable to store encrypted attachment.');
        try {
            $mid = DB::transaction(function () use ($r, $id, $v, $payload, $path) {
                $current=DB::table('communication_conversations')->where('id',$id)->lockForUpdate()->value('protection');
                abort_unless((json_decode($current??'null',true)['key_id']??null)===$payload['key_id'],409,'The chat key changed. Unlock the new key before sending.');
                $mid = DB::table('communication_messages')->insertGetId(['conversation_id'=>$id,'sender_id'=>Access::super($r)?null:auth()->id(),'sender_name'=>Access::super($r)?'Super Admin':auth()->user()->name,'body'=>'Protected message','protected_payload'=>json_encode(array_intersect_key($payload,array_flip(['key_id','iv','ciphertext','file_iv']))),'protected_uuid'=>$v['protected_uuid'],'reply_to'=>$v['reply_to']??null,'attachment_path'=>$path,'attachment_name'=>$path?'encrypted.bin':null,'created_at'=>now(),'updated_at'=>now()]);
                DB::table('communication_conversations')->where('id',$id)->update(['updated_at'=>now()]);
                return $mid;
            });
        } catch (\Throwable $e) {
            if ($path) \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
            throw $e;
        }
        $message = DB::table('communication_messages')->where('id',$mid)->first();
        $message->attachment_url = $path?'/communication/api/attachments/'.$mid:null;
        unset($message->attachment_path);
        $message->reactions=[];
        return response()->json(['id'=>$mid,'message'=>$message],201);
    }

    public function show(Request $r, int $id)
    {
        $c = Access::conversation($r, $id);
        return response()->json(['protection' => json_decode($c->protection ?? 'null'), 'participant' => Protection::participant($r, $c)])->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $r, int $id)
    {
        Access::mutable($r);
        $c = Access::conversation($r, $id);
        abort_unless(Access::personal($r) && Protection::participant($r, $c), 403, 'Only Super Admin can enable chat protection.');
        $v = $r->validate(['key_id'=>'required|uuid', 'iv'=>'required|string|max:32', 'ciphertext'=>'required|string|max:1000', 'future'=>'required|boolean']);
        Protection::validateEnvelope($v);
        DB::transaction(function () use ($id, $v) {
            $c = DB::table('communication_conversations')->where('id', $id)->lockForUpdate()->first();
            $old = json_decode($c->protection ?? 'null', true);
            abort_if($old && ($old['key_id'] !== $v['key_id'] || $old['iv'] !== $v['iv'] || $old['ciphertext'] !== $v['ciphertext']), 409, 'This chat already has a key. Unlock it using the existing key.');
            DB::table('communication_conversations')->where('id', $id)->update(['protection'=>json_encode($v), 'updated_at'=>now()]);
        });
        return response()->json(['protection'=>$v]);
    }
}
