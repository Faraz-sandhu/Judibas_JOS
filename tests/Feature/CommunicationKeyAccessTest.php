<?php
namespace Tests\Feature;

use App\Events\CommunicationChanged;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommunicationKeyAccessTest extends TestCase
{
    use DatabaseTransactions;
    private function user(): User
    {
        $u=User::factory()->create(['is_active'=>true]);
        DB::table('user_product_access')->insert(['user_id'=>$u->id,'product_slug'=>'communication']);
        return $u;
    }
    private function jwk(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/communication-delivery-public.json')),true);
    }
    private function fixture(): array
    {
        Event::fake([CommunicationChanged::class]);
        $user=$this->user();$device=(string)Str::uuid();
        $this->withSession(['judibas_admin'=>false])->actingAs($user)->postJson('/communication/api/protection-devices',['id'=>$device,'public_key'=>$this->jwk()])->assertOk();
        $this->withSession(['judibas_admin'=>true]);
        $id=$this->postJson('/communication/api/conversations?mode=personal',['kind'=>'direct','member_ids'=>[$user->id]])->assertOk()->json('id');
        $config=['key_id'=>(string)Str::uuid(),'iv'=>base64_encode(random_bytes(12)),'ciphertext'=>base64_encode(random_bytes(48)),'future'=>false];
        $this->putJson('/communication/api/conversations/'.$id.'/protection?mode=personal',$config)->assertOk();
        return [$user,$device,$id,$config];
    }
    private function delivery(string $device,array $config): array
    {
        return ['id'=>(string)Str::uuid(),'device_id'=>$device,'key_id'=>$config['key_id'],'ciphertext'=>base64_encode(random_bytes(384))];
    }
    public function test_public_key_registration_rejects_private_keys_and_key_substitution(): void
    {
        [$u,$device,$id,$config]=$this->fixture();
        $this->withSession(['judibas_admin'=>false])->actingAs($u);
        $v=['id'=>(string)Str::uuid(),'public_key'=>$this->jwk()+['d'=>'private-key-value']];
        $this->postJson('/communication/api/protection-devices',$v)->assertStatus(422);
        $this->postJson('/communication/api/protection-devices',['id'=>$device,'public_key'=>$this->jwk(),'secret'=>'never upload'])->assertStatus(422);
        $other=$this->user();$this->actingAs($other)->postJson('/communication/api/protection-devices',['id'=>$device,'public_key'=>$this->jwk()])->assertConflict();
        $this->withSession(['judibas_admin'=>true])->postJson('/communication/api/protection-devices',['id'=>(string)Str::uuid(),'public_key'=>$this->jwk()])->assertForbidden();
    }
    public function test_delivery_is_super_only_and_only_recipient_receives_one_time_envelope(): void
    {
        [$u,$device,$id,$config]=$this->fixture();$v=$this->delivery($device,$config);$root='/communication/api/conversations/'.$id;
        $this->postJson($root.'/key-delivery?mode=review',$v)->assertForbidden();
        $this->postJson($root.'/key-delivery?mode=personal',$v)->assertOk();
        $this->getJson($root.'/key-access?mode=personal')->assertOk()->assertJsonMissingPath('delivery')->assertJsonPath('has_delivery',true);
        $this->getJson($root.'/key-access?mode=review')->assertForbidden();
        $this->withSession(['judibas_admin'=>false])->actingAs($u);
        $this->postJson($root.'/key-delivery',$v)->assertForbidden();
        $this->getJson($root.'/key-access?device_id='.$device)->assertOk()->assertJsonPath('delivery.envelope.ciphertext',$v['ciphertext'])->assertJsonMissingPath('recovery');
        $this->postJson($root.'/key-acknowledge',['device_id'=>$device,'delivery_id'=>$v['id']])->assertOk();
        $this->getJson($root.'/key-access?device_id='.$device)->assertOk()->assertJsonPath('delivery',null)->assertJsonPath('has_history',true);
        $this->assertDatabaseHas('communication_key_deliveries',['id'=>$v['id'],'envelope'=>null]);
        $this->withSession(['judibas_admin'=>true])->postJson($root.'/key-delivery?mode=personal',$this->delivery($device,$config))->assertConflict();
    }
    public function test_forgotten_key_request_notifies_super_admin_and_allows_approved_redelivery(): void
    {
        [$u,$device,$id,$config]=$this->fixture();$root='/communication/api/conversations/'.$id;
        $this->postJson($root.'/key-delivery?mode=personal',$this->delivery($device,$config))->assertOk();
        $this->withSession(['judibas_admin'=>false])->actingAs($u);
        for($i=0;$i<2;$i++)$this->postJson($root.'/key-request',['device_id'=>$device])->assertOk();
        $this->assertSame(1,DB::table('communication_key_requests')->where('conversation_id',$id)->count());
        $this->withSession(['judibas_admin'=>true]);
        $this->getJson('/communication/api?mode=personal')->assertOk()->assertJsonFragment(['key_access_requested'=>true]);
        $this->getJson($root.'/key-access?mode=personal')->assertOk()->assertJsonCount(1,'requests')->assertJsonPath('requests.0.name',$u->name);
        $this->postJson($root.'/key-delivery?mode=personal',$this->delivery($device,$config))->assertOk();
        $this->getJson($root.'/key-access?mode=personal')->assertOk()->assertJsonCount(0,'requests');
        Event::assertDispatched(CommunicationChanged::class,fn($e)=>$e->conversationId===$id && $e->kind==='workspace' && in_array($u->id,$e->recipients) && $e->message===null);
    }
    public function test_recovery_envelope_is_super_only_immutable_and_no_raw_secret_is_accepted(): void
    {
        [$u,$device,$id,$config]=$this->fixture();$root='/communication/api/conversations/'.$id;
        $v=['key_id'=>$config['key_id'],'iv'=>base64_encode(random_bytes(12)),'ciphertext'=>base64_encode(random_bytes(48))];
        $this->postJson($root.'/key-recovery?mode=personal',$v+['recovery_key'=>'must not be stored'])->assertStatus(422);
        $this->postJson($root.'/key-recovery?mode=personal',$v)->assertOk();
        $this->postJson($root.'/key-recovery?mode=personal',$v)->assertOk();
        $changed=$v;$changed['ciphertext']=base64_encode(random_bytes(48));
        $this->postJson($root.'/key-recovery?mode=personal',$changed)->assertConflict();
        $this->getJson($root.'/key-access?mode=personal')->assertOk()->assertJsonPath('recovery.ciphertext',$v['ciphertext']);
        $this->withSession(['judibas_admin'=>false])->actingAs($u)->postJson($root.'/key-recovery',$v)->assertForbidden();
        $this->getJson($root.'/key-access?device_id='.$device)->assertOk()->assertJsonMissingPath('recovery');
        $this->withSession(['judibas_admin'=>true])->getJson($root.'/key-access?mode=review')->assertForbidden();
    }
    public function test_cross_conversation_devices_and_outsiders_cannot_receive_or_consume_keys(): void
    {
        [$u,$device,$id,$config]=$this->fixture();$other=$this->user();$otherDevice=(string)Str::uuid();
        $this->withSession(['judibas_admin'=>false])->actingAs($other)->postJson('/communication/api/protection-devices',['id'=>$otherDevice,'public_key'=>$this->jwk()])->assertOk();
        $root='/communication/api/conversations/'.$id;
        $this->withSession(['judibas_admin'=>true])->postJson($root.'/key-delivery?mode=personal',$this->delivery($otherDevice,$config))->assertForbidden();
        $v=$this->delivery($device,$config);$this->postJson($root.'/key-delivery?mode=personal',$v)->assertOk();
        $this->withSession(['judibas_admin'=>false])->actingAs($other)->getJson($root.'/key-access?device_id='.$device)->assertForbidden();
        $this->postJson($root.'/key-request',['device_id'=>$otherDevice])->assertForbidden();
        $this->postJson($root.'/key-acknowledge',['device_id'=>$device,'delivery_id'=>$v['id']])->assertForbidden();
        $this->actingAs($u)->getJson($root.'/key-access?device_id='.$otherDevice)->assertForbidden();
        DB::table('user_product_access')->where('user_id',$u->id)->delete();
        $this->getJson($root.'/key-access?device_id='.$device)->assertForbidden();
    }
    public function test_new_future_key_preserves_history_recovery_and_pending_old_deliveries(): void
    {
        [$u,$device,$id,$old]=$this->fixture();$root='/communication/api/conversations/'.$id;
        $recovery=['key_id'=>$old['key_id'],'iv'=>base64_encode(random_bytes(12)),'ciphertext'=>base64_encode(random_bytes(48))];
        $this->postJson($root.'/key-recovery?mode=personal',$recovery)->assertOk();
        $delivery=$this->delivery($device,$old);$this->postJson($root.'/key-delivery?mode=personal',$delivery)->assertOk();
        $payload=json_encode(['key_id'=>$old['key_id'],'iv'=>base64_encode(random_bytes(12)),'ciphertext'=>base64_encode(random_bytes(48))]);
        $mid=$this->postJson($root.'/messages?mode=personal',['protected_uuid'=>(string)Str::uuid(),'protected_payload'=>$payload])->assertCreated()->json('id');
        $next=['expected_key_id'=>$old['key_id'],'confirmed'=>true,'key_id'=>(string)Str::uuid(),'iv'=>base64_encode(random_bytes(12)),'ciphertext'=>base64_encode(random_bytes(48))];
        $this->postJson($root.'/protection/rotate?mode=personal',$next)->assertOk()->assertJsonPath('protection.future',true);
        $this->assertDatabaseHas('communication_messages',['id'=>$mid,'protected_payload'=>$payload,'deleted_at'=>null]);
        $this->getJson($root.'/protection/history/'.$old['key_id'].'?mode=personal')->assertOk()->assertJsonPath('protection.key_id',$old['key_id'])->assertJsonPath('recovery.ciphertext',$recovery['ciphertext']);
        $this->getJson($root.'/key-access?mode=personal')->assertOk()->assertJsonPath('recovery',null)->assertJsonPath('has_delivery',false);
        $this->postJson($root.'/messages?mode=personal',['body'=>'unsafe plaintext'])->assertStatus(422);
        $this->postJson($root.'/messages?mode=personal',['protected_uuid'=>(string)Str::uuid(),'protected_payload'=>$payload])->assertStatus(422);
        $current=['key_id'=>$next['key_id'],'iv'=>base64_encode(random_bytes(12)),'ciphertext'=>base64_encode(random_bytes(48))];
        $this->postJson($root.'/key-recovery?mode=personal',$current)->assertOk();
        $this->postJson($root.'/key-delivery?mode=personal',$this->delivery($device,$next))->assertOk();
        $this->getJson($root.'/protection/history/'.$old['key_id'].'?mode=personal')->assertOk()->assertJsonPath('recovery.ciphertext',$recovery['ciphertext']);
        $this->getJson($root.'/protection/history/'.$old['key_id'].'?mode=review')->assertForbidden();
        $this->withSession(['judibas_admin'=>false])->actingAs($u);
        $this->getJson($root.'/key-access?device_id='.$device)->assertOk()->assertJsonPath('delivery.key_id',$next['key_id']);
        $this->getJson($root.'/protection/history/'.$old['key_id'].'?device_id='.$device)->assertOk()->assertJsonPath('recovery',null)->assertJsonPath('delivery.key_id',$old['key_id']);
    }
    public function test_key_reset_requires_super_admin_confirmation_and_current_version(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        [$u,$device,$id,$old]=$this->fixture();$url='/communication/api/conversations/'.$id.'/protection/rotate';
        $v=['expected_key_id'=>$old['key_id'],'confirmed'=>false,'key_id'=>(string)Str::uuid(),'iv'=>base64_encode(random_bytes(12)),'ciphertext'=>base64_encode(random_bytes(48))];
        $this->postJson($url.'?mode=personal',$v)->assertStatus(422);$v['confirmed']=true;
        $this->postJson($url.'?mode=review',$v)->assertForbidden();
        $this->withSession(['judibas_admin'=>false])->actingAs($u)->postJson($url,$v)->assertForbidden();
        $this->withSession(['judibas_admin'=>true]);
        $stale=$v;$stale['expected_key_id']=(string)Str::uuid();
        $this->postJson($url.'?mode=personal',$stale)->assertConflict();
        $same=$v;$same['key_id']=$old['key_id'];$this->postJson($url.'?mode=personal',$same)->assertStatus(422);
        $this->postJson($url.'?mode=personal',$v)->assertOk();
        $this->postJson($url.'?mode=personal',$v)->assertConflict();
        $other=$this->user();$this->withSession(['judibas_admin'=>false])->actingAs($other)->getJson('/communication/api/conversations/'.$id.'/protection/history/'.$old['key_id'])->assertForbidden();
    }

}
