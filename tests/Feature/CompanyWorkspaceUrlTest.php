<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CompanyWorkspace;
use App\Services\UserProductAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyWorkspaceUrlTest extends TestCase
{
    use DatabaseTransactions;

    private int $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->company = DB::table('communication_companies')->insertGetId(['name' => 'Readable Workspace Test', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_company_routes_render_the_correct_product_and_bootstrap_company(): void
    {
        $this->withSession(['judibas_admin' => true]);
        foreach (CompanyWorkspace::PRODUCTS as $root => $product) {
            $this->get('/'.$root.'/readable-workspace-test')->assertOk()->assertViewHas('portal', fn ($p) => $p['workspacePath'] === '/'.$root && $p['companyWorkspace']['id'] === $this->company && $p['product']['slug'] === $product);
        }
        $this->get('/pms/all')->assertOk()->assertViewHas('portal', fn ($p) => $p['companyWorkspace']['id'] === 'all' && $p['pmsBootstrap']['company_context'] === null);
        $this->get('/accounting/all')->assertOk()->assertViewHas('portal', fn ($p) => $p['companyWorkspace']['id'] === 'all');
        $this->get('/canteen/all')->assertNotFound();
        $this->get('/cv-bank/all')->assertNotFound();
    }

    public function test_employee_needs_both_product_permission_and_company_membership(): void
    {
        $user = User::factory()->create(['is_active' => true, 'status' => 1, 'judibas_administrator' => false]);
        UserProductAccess::sync($user, ['product_slugs' => ['projects'], 'company_ids' => [$this->company]]);
        $this->actingAs($user);
        $this->get('/pms/readable-workspace-test')->assertOk();
        $this->get('/accounting/readable-workspace-test')->assertForbidden();
        $other = DB::table('communication_companies')->insertGetId(['name' => 'Other Readable Company', 'created_at' => now(), 'updated_at' => now()]);
        $this->get('/pms/other-readable-company')->assertForbidden();
        $this->get('/pms/nonexistent-workspace-company')->assertNotFound();
        $this->get('/products')->assertOk()->assertViewHas('portal', fn ($p) => count($p['workspaceCompanies']) === 1 && $p['workspaceCompanies'][0]['id'] === $this->company);
    }

    public function test_company_path_takes_precedence_over_conflicting_query_parameters(): void
    {
        $this->withSession(['judibas_admin' => true]);
        $this->get('/pms/readable-workspace-test?workspace_company=999999')->assertOk()->assertViewHas('portal', fn ($p) => $p['pmsBootstrap']['company_context']['id'] === $this->company);
        $this->get('/accounting/readable-workspace-test?company_id=999999')->assertOk()->assertViewHas('portal', fn ($p) => $p['companyWorkspace']['id'] === $this->company);
    }

    public function test_guests_are_sent_to_login_and_old_urls_and_resource_routes_remain_available(): void
    {
        $this->get('/pms/readable-workspace-test')->assertRedirect('/login');
        $this->assertSame('/pms/readable-workspace-test', session('url.intended'));
        $this->withSession(['judibas_admin' => true]);
        $this->get('/pms?workspace_company='.$this->company)->assertOk();
        $this->get('/accounting?company_id='.$this->company)->assertOk();
        $this->getJson('/pms/api')->assertOk()->assertJsonStructure(['permissions']);
        $this->getJson('/canteen/api/bootstrap')->assertOk()->assertJsonStructure(['companies']);
    }

    public function test_names_that_slugify_identically_have_distinct_urls(): void
    {
        $a = DB::table('communication_companies')->insertGetId(['name' => 'URL Test & Company', 'created_at' => now(), 'updated_at' => now()]);
        $b = DB::table('communication_companies')->insertGetId(['name' => 'URL Test Company', 'created_at' => now(), 'updated_at' => now()]);
        $this->withSession(['judibas_admin' => true]);
        $directory = CompanyWorkspace::directory();
        $one = collect($directory)->firstWhere('id', $a);
        $two = collect($directory)->firstWhere('id', $b);
        $this->assertNotSame($one['slug'], $two['slug']);
        $this->get('/pms/'.$one['slug'])->assertOk()->assertViewHas('portal', fn ($p) => $p['companyWorkspace']['id'] === $a);
        $this->get('/pms/'.$two['slug'])->assertOk()->assertViewHas('portal', fn ($p) => $p['companyWorkspace']['id'] === $b);
    }
}
