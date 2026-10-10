<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class PmsFoundationTest extends TestCase {
 use DatabaseTransactions;
 protected function setUp():void {parent::setUp();$this->seed(\App\Modules\Pms\Database\Seeders\PmsFoundationSeeder::class);}
 public function test_departments_keep_source_validation_and_permanent_deletion():void {
  $this->withSession(['judibas_admin'=>true]);
  $this->postJson('/pms/api/departments',['dept_name'=>'Parity Department','description'=>'Original behavior','status'=>1])->assertOk();
  $id=DB::table('pms_departments')->where('dept_name','Parity Department')->value('id');
  $this->postJson('/pms/api/departments',['dept_name'=>'Parity Department','description'=>'Duplicate','status'=>1])->assertUnprocessable();
  $this->postJson('/pms/api/departments/'.$id,['dept_name'=>'Updated Parity Department','description'=>'Updated','status'=>0])->assertOk();
  $this->assertDatabaseHas('pms_departments',['id'=>$id,'status'=>0,'description'=>'Updated']);
  $this->deleteJson('/pms/api/departments/'.$id)->assertOk();$this->assertDatabaseMissing('pms_departments',['id'=>$id]);
 }
 public function test_pms_role_permissions_do_not_grant_other_product_access():void {
  $user=User::factory()->create(['is_active'=>true,'communication_admin'=>true]);
  DB::table('user_product_access')->insert(['user_id'=>$user->id,'product_slug'=>'projects']);
  $this->actingAs($user)->getJson('/pms/api/departments')->assertForbidden();
  $role=DB::table('pms_roles')->insertGetId(['role_name'=>'Parity HR','role_key'=>'parity_hr','created_at'=>now(),'updated_at'=>now()]);
  $permission=DB::table('pms_permissions')->where('permission_key','department-view')->value('id');
  DB::table('pms_permission_roles')->insert(['role_id'=>$role,'permission_id'=>$permission]);DB::table('pms_role_users')->insert(['role_id'=>$role,'user_id'=>$user->id]);
  $this->getJson('/pms/api/departments')->assertOk();$this->getJson('/pms/api/roles')->assertForbidden();
  $this->postJson('/pms/api/departments',['dept_name'=>'Denied','description'=>'Denied','status'=>1])->assertForbidden();
  DB::table('user_product_access')->where('user_id',$user->id)->delete();$this->getJson('/pms/api/departments')->assertForbidden();
 }
 public function test_role_permission_selection_and_update_match_source():void {
  $this->withSession(['judibas_admin'=>true]);$p=DB::table('pms_permissions')->where('permission_key','department-view')->value('id');
  $this->postJson('/pms/api/roles',['role_name'=>'Parity Role','role_key'=>'parity_role','permission_ids'=>[]])->assertUnprocessable();
  $this->postJson('/pms/api/roles',['role_name'=>'Parity Role','role_key'=>'parity_role','permission_ids'=>[$p]])->assertOk();
  $id=DB::table('pms_roles')->where('role_key','parity_role')->value('id');
  $this->getJson('/pms/api/roles')->assertJsonFragment(['role_name'=>'Parity Role']);
  $this->postJson('/pms/api/roles/'.$id,['role_name'=>'Parity Role Updated','role_key'=>'parity_role','permission_ids'=>[$p]])->assertOk();
  $this->deleteJson('/pms/api/roles/'.$id)->assertOk();$this->assertDatabaseMissing('pms_permission_roles',['role_id'=>$id]);
 }
 public function test_login_required_and_inactive_accounts_cannot_open_pms():void {
  $this->get('/pms')->assertRedirect('/login');$this->getJson('/pms/api')->assertForbidden();
  $user=User::factory()->create(['is_active'=>false]);DB::table('user_product_access')->insert(['user_id'=>$user->id,'product_slug'=>'projects']);
  $this->actingAs($user)->getJson('/pms/api')->assertForbidden();
 }
}
