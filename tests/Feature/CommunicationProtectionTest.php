<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommunicationProtectionTest extends TestCase
{
    use DatabaseTransactions;
    private function fixture(): array
    {
        Event::fake([\App\Events\CommunicationChanged::class]);
        Storage::fake('local');
        $u=User::factory()->create(['is_active'=>true]);
        DB::table('user_product_access')->insert(['user_id'=>$u->id,'product_slug'=>'communication']);
        $this->withSession(['judibas_admin'=>true]);
        $id=$this->postJson('/communication/api/conversations?mode=personal',['kind'=>'direct','member_ids'=>[$u->id]])->assertOk()->json('id');
        $config=['key_id'=>(string)Str::uuid(),'iv'=>base64_encode(random_bytes(12)),'ciphertext'=>base64_encode(random_bytes(48)),'future'=>false];
        return [$u,$id,$config];
    }
    private function envelope(array $config): array
    {
        $iv=random_bytes(12);$tag='';
        $cipher=openssl_encrypt('confidential salary text','aes-256-gcm',random_bytes(32),OPENSSL_RAW_DATA,$iv,$tag);
        return ['protected_uuid'=>(string)Str::uuid(),'protected_payload'=>json_encode(['key_id'=>$config['key_id'],'iv'=>base64_encode($iv),'ciphertext'=>base64_encode($cipher.$tag)])];
    }
    public function test_only_super_admin_can_configure_and_keys_cannot_be_replaced(): void
    {
        [$u,$id,$c]=$this->fixture();
        $url='/communication/api/conversations/'.$id.'/protection';
        $this->putJson($url.'?mode=personal',$c)->assertOk();
        $this->putJson($url.'?mode=review',$c)->assertForbidden();
        $other=$c;$other['key_id']=(string)Str::uuid();
        $this->putJson($url.'?mode=personal',$other)->assertConflict();
        $this->withSession(['judibas_admin'=>false])->actingAs($u)->putJson($url,$c)->assertForbidden();
        $this->getJson($url)->assertOk()->assertJsonPath('participant',true);
    }
    public function test_ciphertext_storage_review_and_plaintext_bypass_guards(): void
    {
        [$u,$id,$c]=$this->fixture();$c['future']=true;
        $this->putJson('/communication/api/conversations/'.$id.'/protection?mode=personal',$c)->assertOk();
        $url='/communication/api/conversations/'.$id.'/messages';
        $this->postJson($url.'?mode=personal',['body'=>'plaintext bypass'])->assertStatus(422);
        $envelope=$this->envelope($c);
        $mid=$this->postJson($url.'?mode=personal',$envelope)->assertCreated()->json('id');
        Event::assertDispatched(\App\Events\CommunicationChanged::class, fn ($e) => $e->kind === 'message' && ($e->message['protected_uuid'] ?? null) === $envelope['protected_uuid'] && !str_contains(json_encode($e->broadcastWith()), 'confidential salary text'));
        Event::assertDispatched(\App\Events\CommunicationChanged::class, fn ($e) => $e->kind === 'workspace' && $e->conversationId === $id);
        $this->assertDatabaseHas('communication_messages',['id'=>$mid,'body'=>'Protected message','attachment_name'=>null]);
        $this->assertStringNotContainsString('confidential salary text',json_encode((array)DB::table('communication_messages')->find($mid)));
        $this->getJson($url.'?mode=review')->assertOk()->assertJsonPath('messages.0.protected_payload',$envelope['protected_payload']);
        $this->patchJson('/communication/api/messages/'.$mid.'?mode=personal',['body'=>'replace encrypted'])->assertStatus(422);
        $this->postJson('/communication/api/messages/'.$mid.'/forward?mode=personal',['conversation_id'=>$id])->assertStatus(422);
        $this->withSession(['judibas_admin'=>false])->actingAs($u);
        $this->postJson($url,['body'=>'recipient plaintext bypass'])->assertStatus(422);
        $this->postJson($url,$this->envelope($c))->assertCreated();
        $this->postJson($url,$this->envelope($c)+['body'=>'plaintext metadata'])->assertStatus(422);
        $bad=$this->envelope($c);$p=json_decode($bad['protected_payload'],true);$p['iv']=base64_encode('short');$bad['protected_payload']=json_encode($p);
        $this->postJson($url,$bad)->assertStatus(422);
    }
    public function test_encrypted_files_are_not_exposed_to_review_or_shared_media_and_outsiders_are_denied(): void
    {
        [$u,$id,$c]=$this->fixture();
        $this->putJson('/communication/api/conversations/'.$id.'/protection?mode=personal',$c)->assertOk();
        $v=$this->envelope($c);$p=json_decode($v['protected_payload'],true);$p['file_iv']=base64_encode(random_bytes(12));$v['protected_payload']=json_encode($p);
        $v['attachment']=UploadedFile::fake()->createWithContent('encrypted.bin',random_bytes(128));
        $mid=$this->post('/communication/api/conversations/'.$id.'/messages?mode=personal',$v,['Accept'=>'application/json'])->assertCreated()->json('id');
        $this->get('/communication/api/attachments/'.$mid.'?mode=personal')->assertOk()->assertHeader('Content-Type','application/octet-stream');
        $this->get('/communication/api/attachments/'.$mid.'?mode=review')->assertForbidden();
        $this->getJson('/communication/api/conversations/'.$id.'/shared?kind=docs&mode=personal')->assertOk()->assertJsonCount(0,'items');
        $this->withSession(['judibas_admin'=>false])->actingAs($u)->get('/communication/api/attachments/'.$mid)->assertOk();
        $outsider=User::factory()->create(['is_active'=>true]);DB::table('user_product_access')->insert(['user_id'=>$outsider->id,'product_slug'=>'communication']);
        $this->actingAs($outsider)->getJson('/communication/api/conversations/'.$id.'/messages')->assertForbidden();
    }
}
