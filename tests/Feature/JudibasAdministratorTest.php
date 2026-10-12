<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use App\Services\PlatformAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
class JudibasAdministratorTest extends TestCase
{
 use DatabaseTransactions;
 protected function setUp():void{parent::setUp();$this->withoutVite();Event::fake();}
 private function administrator():User{$u=User::factory()->create(['is_active'=>true]);$u->judibas_administrator=true;$u->save();\App\Services\UserProductAccess::sync($u,['product_slugs'=>[],'company_ids'=>[]]);return $u;}
 public function test_delegate_manages_users_invitations_and_all_product_access():void{
  $u=$this->administrator();$this->actingAs($u);$this->getJson('/admin/api')->assertOk();
  $this->postJson('/admin/api/users',['name'=>'Managed Employee','email'=>'delegate-created@example.test','password'=>'ManagedPass123!','is_active'=>true,'company_ids'=>[],'product_slugs'=>['crm']])->assertOk();
  $this->postJson('/admin/api/invitations',['name'=>'Invited Employee','email'=>'delegate-invited@example.test','company_ids'=>[],'product_slugs'=>['crm'],'delivery_method'=>'manual'])->assertCreated();
  $this->get('/admin')->assertOk()->assertViewHas('portal',fn($p)=>$p['administrator']&&!$p['admin']);
  $this->get('/products')->assertViewHas('portal',fn($p)=>count($p['products'])>2);
  $this->assertTrue(PlatformAccess::product(request(),'crm'));
 }
 public function test_delegate_cannot_grant_role_or_edit_delete_or_invite_admin_accounts():void{
  $u=$this->administrator();$other=$this->administrator();$this->actingAs($u);
  $body=['name'=>'Managed Employee','email'=>'no-escalation@example.test','password'=>'ManagedPass123!','is_active'=>true,'company_ids'=>[],'product_slugs'=>[],'judibas_administrator'=>true];
  $this->postJson('/admin/api/users',$body)->assertForbidden();
  $body['email']=$other->email;$this->postJson('/admin/api/users/'.$other->id,$body)->assertForbidden();$this->deleteJson('/admin/api/users/'.$other->id)->assertForbidden();
  $this->postJson('/admin/api/invitations',['email'=>$other->email,'company_ids'=>[],'product_slugs'=>['crm'],'delivery_method'=>'manual'])->assertForbidden();
  $this->postJson('/pms/api/employees/'.$other->id,[])->assertForbidden();$this->deleteJson('/pms/api/employees/'.$other->id)->assertForbidden();
  $this->postJson('/communication/api/users/'.$other->id,[])->assertForbidden();$this->deleteJson('/communication/api/users/'.$other->id)->assertForbidden();
  $this->getJson('/admin/api/platform-settings')->assertForbidden();$this->postJson('/admin/api/platform-settings/mail',[])->assertForbidden();
 }
 public function test_super_admin_can_grant_and_revoke_the_shared_admin_role():void{
  $u=User::factory()->create(['is_active'=>true]);$body=['name'=>$u->name,'email'=>$u->email,'is_active'=>true,'company_ids'=>[],'product_slugs'=>[],'judibas_administrator'=>true];
  $this->withSession(['judibas_admin'=>true])->postJson('/admin/api/users/'.$u->id,$body)->assertOk();$this->assertTrue($u->fresh()->judibas_administrator);foreach(['communication','projects'] as $slug)$this->assertDatabaseHas('user_product_access',['user_id'=>$u->id,'product_slug'=>$slug]);
  $body['judibas_administrator']=false;$this->postJson('/admin/api/users/'.$u->id,$body)->assertOk();$this->assertFalse($u->fresh()->judibas_administrator);
 }
 public function test_delegate_has_full_pms_visibility_without_a_product_role():void{
  $u=$this->administrator();$this->actingAs($u);$this->getJson('/pms/api')->assertOk()->assertJsonPath('permissions',['*']);
  $this->getJson('/pms/api/projects')->assertOk();$this->getJson('/pms/api/roles')->assertOk();$this->getJson('/pms/api/departments')->assertOk();
  $this->assertTrue(\App\Modules\Pms\Services\PmsAuth::user()->hasRole('admin'));
  $task=DB::table('pms_tasks')->value('id');if($task)$this->getJson('/pms/api/tasks/'.$task)->assertOk();
 }
 public function test_delegate_manages_all_companies_but_cannot_read_other_private_chats_or_calls():void{
  $u=$this->administrator();$this->actingAs($u);$this->getJson('/communication/api/management')->assertOk();
  $this->postJson('/communication/api/companies',['name'=>'Delegate-managed company'])->assertOk();
  $company=DB::table('communication_companies')->where('name','Delegate-managed company')->value('id');
  $this->postJson('/communication/api/announcements',['company_ids'=>[$company],'body'=>'Company announcement'])->assertCreated();
  $person=User::factory()->create(['is_active'=>true]);$other=User::factory()->create(['is_active'=>true]);
  $chat=DB::table('communication_conversations')->insertGetId(['kind'=>'direct','name'=>'Private pair','created_at'=>now(),'updated_at'=>now()]);
  foreach([$person,$other] as $member)DB::table('communication_members')->insert(['conversation_id'=>$chat,'user_id'=>$member->id]);
  $this->getJson('/communication/api/conversations/'.$chat.'/messages')->assertForbidden();$this->getJson('/communication/api?view_user='.$person->id)->assertForbidden();
  $this->getJson('/communication/api/audits')->assertForbidden();$this->getJson('/communication/api/calls?view_user='.$person->id)->assertForbidden();
  config(['broadcasting.default'=>'reverb']);$this->postJson('/broadcasting/auth',['socket_id'=>'1.2','channel_name'=>'private-communication.user.super'])->assertForbidden();
 }
 public function test_administrator_login_uses_its_own_identity_and_snapshot_has_no_review_people():void{
  $u=$this->administrator();$this->post('/login',['email'=>$u->email,'password'=>'password'])->assertRedirect('/admin')->assertSessionMissing('judibas_admin');
  $this->getJson('/communication/api')->assertOk()->assertJsonPath('admin',false)->assertJsonPath('user_id',$u->id)->assertJsonCount(0,'review_people');
 }
 public function test_delegate_can_start_own_chats_and_groups_across_companies():void{
  $u=$this->administrator();$this->actingAs($u);$company=DB::table('communication_companies')->insertGetId(['name'=>'Admin chat company','created_at'=>now(),'updated_at'=>now()]);$peer=User::factory()->create(['is_active'=>true,'company_id'=>$company]);DB::table('user_product_access')->insert(['user_id'=>$peer->id,'product_slug'=>'communication']);
  $id=$this->postJson('/communication/api/conversations',['kind'=>'direct','member_ids'=>[$peer->id]])->assertOk()->json('id');$this->getJson('/communication/api/conversations/'.$id.'/messages')->assertOk();
  $this->postJson('/communication/api/conversations',['kind'=>'group','name'=>'Managed group','company_id'=>$company,'member_ids'=>[$peer->id]])->assertOk();
 }
 public function test_inactive_delegated_admin_loses_every_administrative_capability():void{
  $u=$this->administrator();$u->is_active=false;$u->save();$this->actingAs($u);
  foreach(['/admin/api','/pms/api/projects','/communication/api/management'] as $url)$this->getJson($url)->assertForbidden();
 }
}
