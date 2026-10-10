<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
class PmsMessagingTest extends TestCase {
 use DatabaseTransactions;
 public function test_direct_messages_are_private_paginated_and_marked_read():void {
  Event::fake();$user=\App\Models\User::factory()->create(['is_active'=>true,'status'=>1]);DB::table('user_product_access')->insert(['user_id'=>$user->id,'product_slug'=>'projects']);
  $this->withSession(['judibas_admin'=>true])->getJson('/pms/api/messages')->assertOk()->assertJsonFragment(['name'=>$user->name]);
  for($i=0;$i<32;$i++)$this->postJson('/pms/api/messages/'.$user->id,['body'=>'Message '.$i])->assertCreated();
  $d=$this->getJson('/pms/api/messages/'.$user->id)->assertOk()->json();$this->assertCount(30,$d['messages']);$this->assertTrue($d['has_more']);
  $this->getJson('/pms/api/messages/'.$user->id.'?before='.$d['messages'][0]['id'])->assertOk()->assertJsonCount(2,'messages');
  $this->withSession(['judibas_admin'=>false])->actingAs($user)->getJson('/pms/api/messages')->assertForbidden();
  $role=DB::table('pms_roles')->where('role_key','admin')->value('id');DB::table('pms_role_users')->insert(['user_id'=>$user->id,'role_id'=>$role]);
  $this->getJson('/pms/api/messages')->assertOk()->assertJsonFragment(['id'=>0,'name'=>'Super Admin']);
  $this->postJson('/pms/api/messages/0/seen')->assertOk();$this->assertEquals(0,DB::table('pms_ch_messages')->where('to_id',$user->id)->where('seen',false)->count());
  $this->deleteJson('/pms/api/direct-messages/'.$d['messages'][0]['id'])->assertNotFound();
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
