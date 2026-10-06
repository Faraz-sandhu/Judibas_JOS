<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class CommunicationModuleTest extends TestCase {
 use DatabaseTransactions;
 protected function setUp():void{parent::setUp();$this->withoutVite();}
 public function test_module_management_is_super_admin_only():void{
  $this->getJson('/communication/api/users')->assertForbidden();
  $user=User::factory()->create(['is_active'=>true,'communication_admin'=>true]);
  DB::table('user_product_access')->insert(['user_id'=>$user->id,'product_slug'=>'communication']);
  $this->actingAs($user)->getJson('/communication/api/users')->assertForbidden();
  $this->get('/products/communication')->assertRedirect('/communication');
  $this->withSession(['judibas_admin'=>true])->get('/products/communication')->assertOk();
  $this->getJson('/communication/api/users')->assertOk()->assertJsonMissingPath('blogs');
 }
 public function test_module_changes_preserve_other_product_access_and_accounts():void{
  $company=DB::table('communication_companies')->insertGetId(['name'=>'Module test company','created_at'=>now(),'updated_at'=>now()]);
  $user=User::factory()->create(['is_active'=>true,'company_id'=>$company]);
  $other=collect(config('judibas.products'))->first(fn($p)=>$p['slug']!=='communication')['slug'];
  DB::table('user_product_access')->insert(['user_id'=>$user->id,'product_slug'=>$other]);
  $this->withSession(['judibas_admin'=>true])->postJson('/communication/api/users/'.$user->id,['name'=>$user->name,'email'=>$user->email,'company_id'=>$company,'is_active'=>true,'communication_admin'=>true,'product_slugs'=>['communication']])->assertOk();
  $this->assertDatabaseHas('user_product_access',['user_id'=>$user->id,'product_slug'=>$other]);
  $this->assertDatabaseHas('user_product_access',['user_id'=>$user->id,'product_slug'=>'communication']);
  $this->deleteJson('/communication/api/users/'.$user->id)->assertOk();
  $this->assertDatabaseHas('users',['id'=>$user->id,'communication_admin'=>false]);
  $this->assertDatabaseHas('user_product_access',['user_id'=>$user->id,'product_slug'=>$other]);
  $this->assertDatabaseMissing('user_product_access',['user_id'=>$user->id,'product_slug'=>'communication']);
 }
 public function test_global_account_without_communication_does_not_require_company():void{
  $this->withSession(['judibas_admin'=>true])->postJson('/admin/api/users',['name'=>'Global test employee','email'=>'global-module-test@example.test','password'=>'long-module-test-password','is_active'=>true,'product_slugs'=>[]])->assertOk();
 }
}