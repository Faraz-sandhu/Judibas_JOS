<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
class PmsBootstrapTest extends TestCase {
 use DatabaseTransactions;
 public function test_bootstrap_is_authorized_and_matches_workspace_api():void {
  $this->withoutVite();$this->withSession(['judibas_admin'=>true])->get('/pms')->assertOk()->assertViewHas('portal',fn($p)=>$p['pmsBootstrap']['permissions']===['*']);
  $u=\App\Models\User::factory()->create(['is_active'=>true]);$this->withSession(['judibas_admin'=>false])->actingAs($u)->get('/pms')->assertForbidden();
  DB::table('user_product_access')->insert(['user_id'=>$u->id,'product_slug'=>'projects']);$expected=$this->getJson('/pms/api')->assertOk()->json();
  $this->get('/pms')->assertOk()->assertViewHas('portal',fn($p)=>$p['pmsBootstrap']['permissions']===$expected['permissions']&&$p['pmsBootstrap']['user_id']===$u->id);
 }
}
