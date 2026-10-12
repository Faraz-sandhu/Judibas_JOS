<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
class PlatformUserAccessTest extends TestCase
{
 use DatabaseTransactions;
 protected function setUp():void {parent::setUp();$this->withoutVite();Event::fake();}
 private function access():array {
  $role=DB::table('pms_roles')->insertGetId(['role_name'=>'Central test employee','role_key'=>'central_test_employee','created_at'=>now(),'updated_at'=>now()]);
  $department=DB::table('pms_departments')->insertGetId(['dept_name'=>'Central Engineering','description'=>'Test','status'=>1,'created_at'=>now(),'updated_at'=>now()]);
  $company=DB::table('communication_companies')->insertGetId(['name'=>'Central Company','created_at'=>now(),'updated_at'=>now()]);
  return ['company_ids'=>[$company],'product_slugs'=>['projects','communication'],'pms_role_ids'=>[$role],'pms_department_ids'=>[$department],'communication_admin'=>false,'group_ids'=>[]];
 }
 private function invitation(array $access,string $email='central-invited@example.test'):string {
  $r=$this->withSession(['judibas_admin'=>true])->postJson('/admin/api/invitations',$access+['name'=>'Central Employee','email'=>$email,'delivery_method'=>'manual'])->assertCreated();
  $url=$r->json('invitation_url');$token=basename($url);$this->assertDatabaseHas('platform_invitations',['email'=>$email,'token_hash'=>hash('sha256',$token)]);$this->withSession(['judibas_admin'=>false]);return $token;
 }
 public function test_one_invitation_creates_one_account_with_both_product_assignments():void {
  $a=$this->access();$token=$this->invitation($a);
  $this->postJson('/invitations/'.$token,['name'=>'Central Employee','password'=>'CentralPass123!','password_confirmation'=>'CentralPass123!'])->assertOk();
  $u=User::where('email','central-invited@example.test')->firstOrFail();
  foreach($a['product_slugs'] as $slug)$this->assertDatabaseHas('user_product_access',['user_id'=>$u->id,'product_slug'=>$slug]);
  $this->assertDatabaseHas('pms_role_users',['user_id'=>$u->id,'role_id'=>$a['pms_role_ids'][0]]);
  $this->assertDatabaseHas('pms_user_departments',['user_id'=>$u->id,'dept_id'=>$a['pms_department_ids'][0]]);
  $this->assertDatabaseHas('communication_company_memberships',['user_id'=>$u->id,'company_id'=>$a['company_ids'][0]]);
  $this->postJson('/invitations/'.$token,[])->assertStatus(410);
  $this->getJson('/pms/api/realtime/config')->assertOk();$this->getJson('/communication/api')->assertOk();
 }
 public function test_existing_account_requires_its_owner_and_preserves_password_and_other_products():void {
  $a=$this->access();$u=User::factory()->create(['email'=>'existing-central@example.test','is_active'=>true]);$password=$u->password;
  DB::table('user_product_access')->insert(['user_id'=>$u->id,'product_slug'=>'crm']);
  $token=$this->invitation($a,$u->email);$this->postJson('/invitations/'.$token,[])->assertForbidden();
  $other=User::factory()->create(['is_active'=>true]);$this->actingAs($other)->postJson('/invitations/'.$token,[])->assertForbidden();
  $this->actingAs($u)->postJson('/invitations/'.$token,[])->assertOk();
  $this->assertSame($password,$u->fresh()->password);$this->assertSame(1,User::where('email',$u->email)->count());
  $this->assertDatabaseHas('user_product_access',['user_id'=>$u->id,'product_slug'=>'crm']);
 }
 public function test_revoked_and_expired_invitations_do_not_create_accounts():void {
  $token=$this->invitation($this->access());$id=DB::table('platform_invitations')->where('token_hash',hash('sha256',$token))->value('id');
  $this->withSession(['judibas_admin'=>true])->postJson('/admin/api/invitations/'.$id.'/revoke',[])->assertOk();$this->withSession(['judibas_admin'=>false]);
  $this->postJson('/invitations/'.$token,[])->assertStatus(410);
  DB::table('platform_invitations')->where('id',$id)->update(['revoked_at'=>null,'expires_at'=>now()->subMinute()]);
  $this->postJson('/invitations/'.$token,[])->assertStatus(410);$this->assertDatabaseMissing('users',['email'=>'central-invited@example.test']);
 }
 public function test_pms_admin_cannot_manage_central_accounts_or_onboarding():void {
  $u=User::factory()->create(['is_active'=>true]);DB::table('user_product_access')->insert(['user_id'=>$u->id,'product_slug'=>'projects']);
  $role=DB::table('pms_roles')->where('role_key','admin')->value('id');DB::table('pms_role_users')->insert(['role_id'=>$role,'user_id'=>$u->id]);$this->actingAs($u);
  $this->getJson('/admin/api')->assertForbidden();$this->postJson('/admin/api/invitations',[])->assertForbidden();
  foreach(['/pms/api/employees','/pms/api/invitations/employees'] as $path)$this->postJson($path,[])->assertForbidden();
 }
 public function test_central_user_save_and_product_removal_preserve_pms_assignments():void {
  $a=$this->access();$u=User::factory()->create(['is_active'=>true]);$body=$a+['name'=>'Central Employee','email'=>$u->email,'password'=>'','is_active'=>true];
  $this->withSession(['judibas_admin'=>true])->postJson('/admin/api/users/'.$u->id,$body)->assertOk();
  $this->getJson('/admin/api')->assertOk()->assertJsonFragment(['email'=>$u->email]);
  $body['product_slugs']=['communication'];$this->postJson('/admin/api/users/'.$u->id,$body)->assertOk();
  $this->assertDatabaseMissing('user_product_access',['user_id'=>$u->id,'product_slug'=>'projects']);
  $this->assertDatabaseHas('pms_role_users',['user_id'=>$u->id,'role_id'=>$a['pms_role_ids'][0]]);
  $this->withSession(['judibas_admin'=>false])->actingAs($u)->getJson('/pms/api/projects')->assertForbidden();
 }
 public function test_multiple_companies_groups_and_tasks_are_applied_without_removing_existing_assignees():void {
  $a=$this->access();$a['company_ids'][]=DB::table('communication_companies')->insertGetId(['name'=>'Second central company','created_at'=>now(),'updated_at'=>now()]);
  $group=DB::table('communication_conversations')->insertGetId(['kind'=>'group','name'=>'Central team','company_id'=>$a['company_ids'][1],'created_at'=>now(),'updated_at'=>now()]);$a['group_ids']=[$group];
  $project=DB::table('pms_projects')->insertGetId(['name'=>'Central project','status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
  $task=DB::table('pms_tasks')->insertGetId(['title'=>'Central task','project_id'=>$project,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);$a['pms_task_ids']=[$task];
  $old=User::factory()->create(['is_active'=>true]);DB::table('pms_task_user')->insert(['user_id'=>$old->id,'task_id'=>$task]);
  $token=$this->invitation($a,'multi-central@example.test');$this->postJson('/invitations/'.$token,['name'=>'Multi Employee','password'=>'CentralPass123!','password_confirmation'=>'CentralPass123!'])->assertOk();$u=User::where('email','multi-central@example.test')->firstOrFail();
  foreach($a['company_ids'] as $id)$this->assertDatabaseHas('communication_company_memberships',['user_id'=>$u->id,'company_id'=>$id]);
  $this->assertDatabaseHas('communication_members',['user_id'=>$u->id,'conversation_id'=>$group]);
  $this->assertDatabaseHas('pms_task_user',['user_id'=>$u->id,'task_id'=>$task]);$this->assertDatabaseHas('pms_task_user',['user_id'=>$old->id,'task_id'=>$task]);
  $this->assertDatabaseHas('pms_project_user',['user_id'=>$u->id,'project_id'=>$project]);
 }
 public function test_existing_user_sign_in_returns_to_the_invitation():void {
  $u=User::factory()->create(['email'=>'signin-central@example.test','is_active'=>true,'password'=>'ExistingPass123!']);
  $token=$this->invitation($this->access(),$u->email);$this->get('/invitations/'.$token)->assertOk();
  $this->post('/login',['email'=>$u->email,'password'=>'ExistingPass123!'])->assertRedirect('/invitations/'.$token);
  $this->getJson('/invitations/'.$token.'/details')->assertJsonPath('can_accept',true);
 }
 public function test_employee_product_directory_only_shows_assigned_products():void {
  $u=User::factory()->create(['is_active'=>true]);DB::table('user_product_access')->insert(['user_id'=>$u->id,'product_slug'=>'projects']);
  $this->actingAs($u)->get('/products')->assertOk()->assertViewHas('portal',fn($p)=>array_column($p['products'],'slug')===['projects']);
  $this->get('/products/communication')->assertNotFound();
 }
 public function test_roles_are_managed_by_pms_permissions_without_granting_global_admin():void {
  $u=User::factory()->create(['is_active'=>true]);DB::table('user_product_access')->insert(['user_id'=>$u->id,'product_slug'=>'projects']);$this->actingAs($u);
  $this->getJson('/pms/api/roles')->assertForbidden();$this->getJson('/pms/api/permissions')->assertForbidden();
  $role=DB::table('pms_roles')->where('role_key','admin')->value('id');DB::table('pms_role_users')->insert(['user_id'=>$u->id,'role_id'=>$role]);
  $this->getJson('/pms/api/roles')->assertOk();$this->getJson('/pms/api/permissions')->assertOk();$this->getJson('/admin/api')->assertForbidden();
 }
 public function test_invalid_product_role_and_company_selection_are_rejected():void {
  $a=$this->access();$this->withSession(['judibas_admin'=>true]);
  foreach([['product_slugs'=>['unknown']],['pms_role_ids'=>[]],['company_ids'=>[]],['pms_department_ids'=>[999999999]]] as $override)$this->postJson('/admin/api/invitations',array_replace($a,$override)+['email'=>'invalid@example.test','delivery_method'=>'manual'])->assertUnprocessable();
 }
}
