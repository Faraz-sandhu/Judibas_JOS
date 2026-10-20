<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use App\Modules\Daymark\Models\Report;
use App\Modules\Daymark\Events\ReportChanged;
use App\Modules\Daymark\Services\Realtime;
use App\Services\UserProductAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB,Event,Broadcast};
class DaymarkRealtimeTest extends TestCase {
 use DatabaseTransactions;
 private int $company;
 protected function setUp():void{parent::setUp();Event::fake();$this->company=DB::table('communication_companies')->insertGetId(['name'=>'Realtime test','created_at'=>now(),'updated_at'=>now()]);}
 private function member(string $role='employee',?int $company=null):User{$u=User::factory()->create(['is_active'=>true]);UserProductAccess::sync($u,['product_slugs'=>['daymark'],'company_ids'=>[$company??$this->company],'daymark_role'=>$role]);return $u;}
 public function test_submission_and_review_broadcast_to_owner_assigned_managers_leadership_and_admins_only():void{
  $owner=$this->member();$manager=$this->member('pm');$unassigned=$this->member('pm');$lead=$this->member('hr');$peer=$this->member();
  $otherCompany=DB::table('communication_companies')->insertGetId(['name'=>'Other realtime','created_at'=>now(),'updated_at'=>now()]);$foreign=$this->member('ceo',$otherCompany);
  $admin=User::factory()->create(['is_active'=>true,'judibas_administrator'=>true]);DB::table('daymark_assignments')->insert(['manager_id'=>$manager->id,'employee_id'=>$owner->id]);
  $payload=['date'=>today()->toDateString(),'status'=>'submitted','hours'=>8,'completed'=>'Done','progress'=>'Testing','plan'=>'Release','blockers'=>'None'];
  $this->actingAs($owner)->postJson('/daymark/api/report',$payload)->assertOk();
  $report=Report::where('user_id',$owner->id)->firstOrFail();$ids=Realtime::recipients($report);
  foreach([$owner,$manager,$lead,$admin] as $recipient)$this->assertContains((string)$recipient->id,$ids);
  foreach([$unassigned,$peer,$foreign] as $recipient)$this->assertNotContains((string)$recipient->id,$ids);
  $this->assertContains('super',$ids);
  DB::table('communication_company_memberships')->where('user_id',$manager->id)->delete();DB::table('users')->where('id',$manager->id)->update(['company_id'=>null]);$this->assertNotContains((string)$manager->id,Realtime::recipients($report));DB::table('users')->where('id',$manager->id)->update(['company_id'=>$this->company]);DB::table('communication_company_memberships')->insert(['user_id'=>$manager->id,'company_id'=>$this->company]);
  Event::assertDispatched(ReportChanged::class,fn($event)=>$event->reportId===$report->id&&$event->action==='submitted'&&in_array('super',$event->recipients));
  $this->actingAs($manager)->postJson('/daymark/api/review',['id'=>$report->id,'status'=>'changes_requested','feedback'=>'More details'])->assertOk();
  Event::assertDispatched(ReportChanged::class,fn($event)=>$event->action==='changes_requested'&&in_array((string)$owner->id,$event->recipients));
  $event=new ReportChanged($ids,$report->id,'submitted');$this->assertSame(['report_id'=>$report->id,'action'=>'submitted'],$event->broadcastWith());foreach($event->broadcastOn() as $channel)$this->assertStringStartsWith('private-daymark.user.',$channel->name);
 }
 public function test_draft_changes_are_sent_only_to_the_author_and_failed_changes_do_not_broadcast():void{
  $owner=$this->member();$this->member('company_admin');$payload=['date'=>today()->toDateString(),'status'=>'draft','hours'=>8,'completed'=>'Private','progress'=>'','plan'=>'','blockers'=>''];
  $this->actingAs($owner)->postJson('/daymark/api/report',$payload)->assertOk();
  Event::assertDispatched(ReportChanged::class,fn($e)=>$e->recipients===[(string)$owner->id]);
  Event::fake();$this->postJson('/daymark/api/report',array_replace($payload,['status'=>'submitted']))->assertStatus(422);Event::assertNotDispatched(ReportChanged::class);
 }
 public function test_private_auth_is_product_scoped_and_supports_super_admin_and_both_providers():void{
  config(['broadcasting.default'=>'reverb']);$body=['socket_id'=>'123.456','channel_name'=>'private-daymark.user.super'];$this->postJson('/daymark/api/realtime/auth',$body)->assertForbidden();
  $owner=$this->member();$this->actingAs($owner)->postJson('/daymark/api/realtime/auth',$body)->assertForbidden();$body['channel_name']='private-daymark.user.'.$owner->id;$this->postJson('/daymark/api/realtime/auth',$body)->assertOk()->assertJsonStructure(['auth']);
  $config=$this->getJson('/daymark/api/realtime/config')->assertOk()->json();$this->assertSame('daymark.user.'.$owner->id,$config['channel']);$this->assertArrayNotHasKey('secret',$config);$this->assertArrayNotHasKey('app_secret',$config);
  config(['broadcasting.default'=>'pusher','broadcasting.connections.pusher'=>['driver'=>'pusher','key'=>'test-key','secret'=>'test-secret','app_id'=>'test-app','options'=>['cluster'=>'mt1','useTLS'=>true]]]);Broadcast::forgetDrivers();$this->postJson('/daymark/api/realtime/auth',$body)->assertOk()->assertJsonStructure(['auth']);
  $this->withSession(['judibas_admin'=>true])->postJson('/daymark/api/realtime/auth',['socket_id'=>'123.456','channel_name'=>'private-daymark.user.super'])->assertOk();$this->postJson('/daymark/api/realtime/auth',$body)->assertForbidden();
  $this->withSession(['judibas_admin'=>false]);DB::table('user_product_access')->where('user_id',$owner->id)->delete();$this->postJson('/daymark/api/realtime/auth',$body)->assertForbidden();
 }
}
