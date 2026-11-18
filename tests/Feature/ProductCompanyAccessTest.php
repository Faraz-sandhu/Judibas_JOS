<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProductCompanyAccess;
use App\Services\UserProductAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProductCompanyAccessTest extends TestCase
{
    use DatabaseTransactions;
    private int $a;
    private int $b;
    private int $role;

    protected function setUp(): void
    {
        parent::setUp();$this->withoutVite();Event::fake();
        $this->a=DB::table('communication_companies')->insertGetId(['name'=>'Product scope Alpha']);
        $this->b=DB::table('communication_companies')->insertGetId(['name'=>'Product scope Beta']);
        $this->role=DB::table('pms_roles')->where('role_key','project_manager')->value('id');
        config(['product-catalogue.groups'=>[['id'=>'pms','name'=>'PMS','slug'=>'projects','planned'=>false,'description'=>'Projects','companies'=>['Product scope Alpha','Product scope Beta']]]]);
    }

    private function payload(): array
    {
        return ['email'=>'separate-scopes@example.test','name'=>'Scoped employee','delivery_method'=>'manual',
            'product_slugs'=>['projects','communication','cv-bank','canteen','accounts'],
            'company_ids'=>[$this->a,$this->b],
            'product_company_ids'=>['projects'=>[$this->a],'communication'=>[$this->a,$this->b],'cv-bank'=>[$this->b],'canteen'=>[$this->a],'accounts'=>[$this->b]],
            'pms_role_ids'=>[$this->role],'pms_department_ids'=>[],'pms_task_ids'=>[], 'canteen_role'=>'employee','accounting_role'=>'viewer'];
    }

    private function invited(): User
    {
        $url=$this->withSession(['judibas_admin'=>true])->postJson('/admin/api/invitations',$this->payload())->assertCreated()->json('invitation_url');
        $this->withSession(['judibas_admin'=>false])->postJson('/invitations/'.basename($url),[
            'name'=>'Scoped employee','password'=>'ScopedPass123!','password_confirmation'=>'ScopedPass123!',
        ])->assertOk();
        return User::where('email','separate-scopes@example.test')->firstOrFail();
    }

    public function test_employee_editor_loads_and_replaces_product_scopes_independently(): void
    {
        $user=$this->invited();
        $rows=$this->withSession(['judibas_admin'=>true])->getJson('/admin/api')->assertOk()->json('users');
        $row=collect($rows)->firstWhere('id',$user->id);
        $this->assertEquals([$this->a],$row['product_company_ids']['projects']);
        $this->assertEquals([$this->a,$this->b],$row['product_company_ids']['communication']);
        $payload=$this->payload();unset($payload['delivery_method'],$payload['pms_task_ids']);
        $payload['is_active']=true;$payload['password']='';
        $payload['product_company_ids']['communication']=[$this->b];
        $this->postJson('/admin/api/users/'.$user->id,$payload)->assertOk();
        $this->assertEquals([$this->a],ProductCompanyAccess::ids($user->id,'projects'));
        $this->assertEquals([$this->b],ProductCompanyAccess::ids($user->id,'communication'));
    }

    public function test_invitation_acceptance_saves_each_scope_and_retains_overlap(): void
    {
        $user=$this->invited();
        foreach($this->payload()['product_company_ids'] as $slug=>$ids)$this->assertEquals($ids,ProductCompanyAccess::ids($user->id,$slug));
        $this->assertDatabaseHas('pms_role_users',['user_id'=>$user->id,'role_id'=>$this->role]);
        $this->assertDatabaseHas('canteen_profiles',['user_id'=>$user->id,'company_id'=>$this->a]);
        $this->assertDatabaseMissing('canteen_profiles',['user_id'=>$user->id,'company_id'=>$this->b]);
    }

    public function test_product_endpoints_and_direct_company_urls_reject_other_product_memberships(): void
    {
        $user=$this->invited();$this->actingAs($user);
        $this->getJson('/cv-bank/data?company_id='.$this->b)->assertOk()->assertJsonCount(1,'companies');
        $this->getJson('/cv-bank/data?company_id='.$this->a)->assertForbidden();
        $this->postJson('/cv-bank/departments',['company_id'=>$this->a,'name'=>'Forbidden department','active'=>true])->assertForbidden();
        $this->get('/cv-bank/product-scope-alpha')->assertForbidden();
        $this->get('/pms/product-scope-beta')->assertForbidden();
        $this->getJson('/canteen/api/bootstrap')->assertOk()->assertJsonCount(1,'companies')->assertJsonPath('companies.0.id',$this->a);
        $this->assertEquals([$this->b],\App\Modules\Accounting\Services\AccountingAccess::companies(request())->pluck('id')->all());
        $this->get('/products')->assertOk()->assertViewHas('portal',fn($p)=>array_column($p['catalogueGroups'][0]['companies'],'name')===['Product scope Alpha']);
    }

    public function test_company_employee_queries_do_not_reuse_another_products_memberships(): void
    {
        $user=$this->invited();
        $query=DB::table('users');ProductCompanyAccess::users($query,'cv-bank',[$this->a]);
        $this->assertNotContains($user->id,$query->pluck('id')->all());
        $query=DB::table('users');ProductCompanyAccess::users($query,'communication',[$this->a]);
        $this->assertContains($user->id,$query->pluck('id')->all());
    }

    public function test_new_invitation_does_not_expand_existing_legacy_product_scope(): void
    {
        $user=User::factory()->create(['is_active'=>true,'company_id'=>$this->a]);
        DB::table('user_product_access')->insert(['user_id'=>$user->id,'product_slug'=>'projects']);
        UserProductAccess::sync($user,['product_slugs'=>['communication'],'company_ids'=>[$this->b],'product_company_ids'=>['communication'=>[$this->b]]],true);
        $this->assertEquals([$this->a],ProductCompanyAccess::ids($user->id,'projects'));
        $this->assertEquals([$this->b],ProductCompanyAccess::ids($user->id,'communication'));
    }

    public function test_reinviting_merges_only_the_target_products_company_scope(): void
    {
        $user=$this->invited();
        UserProductAccess::sync($user,['product_slugs'=>['cv-bank'],'company_ids'=>[$this->a],'product_company_ids'=>['cv-bank'=>[$this->a]]],true);
        $this->assertEqualsCanonicalizing([$this->a,$this->b],ProductCompanyAccess::ids($user->id,'cv-bank'));
        $this->assertEquals([$this->a],ProductCompanyAccess::ids($user->id,'projects'));
        $this->assertEquals([$this->b],ProductCompanyAccess::ids($user->id,'accounts'));
    }

    public function test_invalid_missing_and_out_of_directory_scopes_are_rejected(): void
    {
        $body=$this->payload();unset($body['product_company_ids']['cv-bank']);
        $this->withSession(['judibas_admin'=>true])->postJson('/admin/api/invitations',$body)->assertUnprocessable()->assertJsonValidationErrors('product_company_ids');
        $body=$this->payload();$body['product_company_ids']['projects']=[];
        $this->postJson('/admin/api/invitations',$body)->assertUnprocessable()->assertJsonValidationErrors('product_company_ids.projects');
        $body=$this->payload();config(['product-catalogue.groups.0.companies'=>['Product scope Beta']]);
        $this->postJson('/admin/api/invitations',$body)->assertUnprocessable()->assertJsonValidationErrors('product_company_ids.projects');
    }

    public function test_explicit_empty_scope_never_falls_back_to_shared_memberships(): void
    {
        $user=User::factory()->create(['is_active'=>true,'company_id'=>$this->a]);
        DB::table('user_product_access')->insert(['user_id'=>$user->id,'product_slug'=>'cv-bank','company_ids'=>'[]']);
        $this->assertSame([],ProductCompanyAccess::ids($user->id,'cv-bank'));
        $this->actingAs($user)->getJson('/cv-bank/data?company_id='.$this->a)->assertOk()->assertJsonCount(0,'companies');
        $this->postJson('/cv-bank/departments',['company_id'=>$this->a,'name'=>'Forbidden','active'=>true])->assertForbidden();
        $query=DB::table('users');ProductCompanyAccess::users($query,'cv-bank',[$this->a]);
        $this->assertNotContains($user->id,$query->pluck('id')->all());
    }

    public function test_legacy_central_editor_cannot_expand_product_scopes_with_shared_company_ids(): void
    {
        $user=$this->invited();
        $this->withSession(['judibas_admin'=>true])->postJson('/admin/api/users/'.$user->id,[
            'name'=>$user->name,'email'=>$user->email,'password'=>'','is_active'=>true,
            'product_slugs'=>$this->payload()['product_slugs'],'company_ids'=>[$this->a,$this->b],
        ])->assertOk();
        $this->assertSame([$this->a],ProductCompanyAccess::ids($user->id,'projects'));
        $this->assertSame([$this->b],ProductCompanyAccess::ids($user->id,'cv-bank'));
        $this->assertSame([$this->b],ProductCompanyAccess::ids($user->id,'accounts'));
    }

    public function test_pms_task_assignment_cannot_use_communication_company_access(): void
    {
        $pmsCompany=DB::table('pms_companies')->insertGetId(['name'=>'Task company','platform_company_id'=>$this->b]);
        $project=DB::table('pms_projects')->insertGetId(['name'=>'Other company project','company_id'=>$pmsCompany,'status'=>'pending']);
        $task=DB::table('pms_tasks')->insertGetId(['title'=>'Other company task','project_id'=>$project,'status'=>'pending']);
        $body=$this->payload();$body['pms_task_ids']=[$task];
        $this->withSession(['judibas_admin'=>true])->postJson('/admin/api/invitations',$body)->assertUnprocessable()->assertJsonValidationErrors('pms_task_ids');
    }

    public function test_numeric_string_ids_are_normalized_for_company_directory_queries(): void
    {
        $user=User::factory()->create(['is_active'=>true]);
        UserProductAccess::sync($user,['product_slugs'=>['cv-bank'],'company_ids'=>[(string)$this->a],'product_company_ids'=>['cv-bank'=>[(string)$this->a]]]);
        $stored=DB::table('user_product_access')->where('user_id',$user->id)->where('product_slug','cv-bank')->value('company_ids');
        $this->assertSame([$this->a],json_decode($stored,true));
        $query=DB::table('users');ProductCompanyAccess::users($query,'cv-bank',[$this->a]);
        $this->assertContains($user->id,$query->pluck('id')->all());
    }

    public function test_existing_employee_can_accept_a_task_they_could_not_previously_view(): void
    {
        $user=$this->invited();
        $employee=DB::table('pms_roles')->where('role_key','employee')->value('id');
        DB::table('pms_role_users')->where('user_id',$user->id)->delete();
        DB::table('pms_role_users')->insert(['user_id'=>$user->id,'role_id'=>$employee]);
        $company=DB::table('pms_companies')->where('platform_company_id',$this->a)->value('id')
            ??DB::table('pms_companies')->insertGetId(['name'=>'Product scope Alpha','platform_company_id'=>$this->a]);
        $project=DB::table('pms_projects')->insertGetId(['company_id'=>$company,'name'=>'New assignment','status'=>'pending']);
        $task=DB::table('pms_tasks')->insertGetId(['project_id'=>$project,'title'=>'Not previously assigned','status'=>'pending']);
        $url=$this->withSession(['judibas_admin'=>true])->postJson('/admin/api/invitations',[
            'email'=>$user->email,'delivery_method'=>'manual','product_slugs'=>['projects'],
            'company_ids'=>[$this->a],'product_company_ids'=>['projects'=>[$this->a]],
            'pms_role_ids'=>[$employee],'pms_department_ids'=>[],'pms_task_ids'=>[$task],
        ])->assertCreated()->json('invitation_url');
        $this->withSession(['judibas_admin'=>false])->actingAs($user)->postJson('/invitations/'.basename($url),[])->assertOk();
        $this->assertDatabaseHas('pms_task_user',['user_id'=>$user->id,'task_id'=>$task]);
        $this->assertDatabaseHas('pms_project_user',['user_id'=>$user->id,'project_id'=>$project]);
        $this->assertSame([$this->b],ProductCompanyAccess::ids($user->id,'cv-bank'));
    }
}
