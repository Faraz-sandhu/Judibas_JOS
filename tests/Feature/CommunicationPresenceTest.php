<?php
namespace Tests\Feature;
use App\Models\User;
use App\Events\CommunicationPresence;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
class CommunicationPresenceTest extends TestCase {
 use DatabaseTransactions;
 public function test_manual_status_is_persistent_live_and_scoped_to_eligible_employees(): void {
  Event::fake([CommunicationPresence::class]);
  $company=DB::table('communication_companies')->insertGetId(['name'=>'Manual Status Test','created_at'=>now(),'updated_at'=>now()]);
  $a=User::factory()->create(['company_id'=>$company,'is_active'=>true]);$b=User::factory()->create(['company_id'=>$company,'is_active'=>true]);$outsider=User::factory()->create(['is_active'=>true]);
  foreach([$a,$b,$outsider] as $u)DB::table('user_product_access')->insert(['user_id'=>$u->id,'product_slug'=>'communication']);
  $tab='10000000-1000-4000-8000-100000000001';
  $this->actingAs($a)->postJson('/communication/api/presence',['tab'=>$tab,'activity'=>'online'])->assertOk()->assertJsonPath('state','online');
  foreach(['away','busy','offline','online'] as $state){
   $this->actingAs($a)->postJson('/communication/api/presence',['state'=>$state])->assertOk()->assertJsonPath('state',$state);
   $this->assertDatabaseHas('users',['id'=>$a->id,'communication_status'=>$state]);
   $this->actingAs($b)->getJson('/communication/api/presence')->assertOk()->assertJsonPath('states.'.$a->id,$state)->assertJsonMissingPath('states.'.$outsider->id);
  }
  $this->actingAs($outsider)->getJson('/communication/api/presence')->assertJsonMissingPath('states.'.$a->id);
  $this->actingAs($a)->postJson('/communication/api/presence',['state'=>'invalid'])->assertUnprocessable();
  Event::assertDispatchedTimes(CommunicationPresence::class,5);
  $this->actingAs($a)->postJson('/communication/api/presence',['tab'=>$tab,'activity'=>'away'])->assertOk()->assertJsonPath('state','away')->assertJsonPath('preference','online');
  $this->postJson('/communication/api/presence',['tab'=>$tab,'activity'=>'offline'])->assertOk()->assertJsonPath('state','offline');
  $this->assertDatabaseHas('users',['id'=>$a->id,'communication_status'=>'online']);
  Event::assertDispatched(CommunicationPresence::class,fn($e)=>$e->userId===$a->id&&$e->state==='away');
  $this->withSession(['judibas_admin'=>true])->postJson('/communication/api/presence',['state'=>'online'])->assertForbidden();
 }
 public function test_multiple_tabs_and_manual_busy_are_preserved(): void {
  Event::fake([CommunicationPresence::class]);$u=User::factory()->create(['is_active'=>true]);DB::table('user_product_access')->insert(['user_id'=>$u->id,'product_slug'=>'communication']);$this->actingAs($u);
  $a='10000000-1000-4000-8000-100000000011';$b='10000000-1000-4000-8000-100000000012';
  $this->postJson('/communication/api/presence',['tab'=>$a,'activity'=>'online'])->assertOk()->assertJsonPath('state','online');
  $this->postJson('/communication/api/presence',['tab'=>$b,'activity'=>'away'])->assertOk()->assertJsonPath('state','online');
  $this->postJson('/communication/api/presence',['tab'=>$a,'activity'=>'offline'])->assertOk()->assertJsonPath('state','away');
  $this->postJson('/communication/api/presence',['state'=>'busy'])->assertOk()->assertJsonPath('state','busy');
  $this->postJson('/communication/api/presence',['tab'=>$b,'activity'=>'online'])->assertOk()->assertJsonPath('state','busy');
  $this->postJson('/communication/api/presence',['tab'=>$b,'activity'=>'offline'])->assertOk()->assertJsonPath('state','offline')->assertJsonPath('preference','busy');
  $this->postJson('/communication/api/presence',['tab'=>$a,'activity'=>'online'])->assertOk()->assertJsonPath('state','busy');
  \Illuminate\Support\Facades\Cache::forget('communication.presence.tabs.'.$u->id);
 }
 public function test_presence_requires_active_communication_access(): void {
  $this->getJson('/communication/api/presence')->assertForbidden();
  $u=User::factory()->create(['is_active'=>true]);$this->actingAs($u)->getJson('/communication/api/presence')->assertForbidden();
 }
}
