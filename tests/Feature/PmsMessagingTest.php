<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
class PmsMessagingTest extends TestCase {
 use DatabaseTransactions;
 public function test_direct_messaging_remains_disabled():void {
  $user=\App\Models\User::factory()->create(['is_active'=>true,'status'=>1]);DB::table('user_product_access')->insert(['user_id'=>$user->id,'product_slug'=>'projects']);
  $this->withSession(['judibas_admin'=>true])->getJson('/pms/api/messages')->assertNotFound();
  $this->postJson('/pms/api/messages/'.$user->id,['body'=>'Disabled message'])->assertNotFound();
  $this->withSession(['judibas_admin'=>false])->actingAs($user)->getJson('/pms/api/messages')->assertNotFound();
 }
 public function test_realtime_auth_is_scoped_and_configuration_does_not_expose_secrets():void {
  $this->withSession(['judibas_admin'=>true]);$cfg=$this->getJson('/pms/api/realtime/config')->assertOk()->json();$this->assertArrayNotHasKey('secret',$cfg);$this->assertArrayNotHasKey('app_secret',$cfg);
  $this->postJson('/pms/api/broadcasting/auth',['socket_id'=>'123.456','channel_name'=>'private-pms.direct.123'])->assertForbidden();
  $this->postJson('/pms/api/broadcasting/auth',['socket_id'=>'123.456','channel_name'=>'private-pms.direct.0'])->assertOk();
  $this->postJson('/pms/api/settings/realtime',['enabled'=>false])->assertOk();$this->getJson('/pms/api/realtime/config')->assertOk()->assertJsonPath('enabled',false);
  $this->postJson('/pms/api/broadcasting/auth',['socket_id'=>'123.456','channel_name'=>'private-pms.direct.0'])->assertUnprocessable();
  cache()->forget('pms.realtime.settings');
 }
}
