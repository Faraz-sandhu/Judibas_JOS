<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProductCatalogue;
use App\Services\UserProductAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCatalogueTest extends TestCase
{
    use DatabaseTransactions;

    public function test_crm_opens_the_external_application_without_linking_to_pms(): void
    {
        $this->withoutVite();
        $company=DB::table('communication_companies')->where('name','Cruxlo')->first();
        $id=$company?->id??DB::table('communication_companies')->insertGetId(['name'=>'Cruxlo']);
        $user=User::factory()->create(['is_active'=>true]);
        UserProductAccess::sync($user,['product_slugs'=>['crm'],'company_ids'=>[$id]]);
        $this->actingAs($user)->get('/products')->assertOk()->assertViewHas('portal',function($portal){
            $group=collect($portal['catalogueGroups'])->firstWhere('slug','crm');
            return $group && !$group['planned'] && $group['allHref']==='https://crm.judibas.com/'
                && $group['externalUrl']==='https://crm.judibas.com/' && $group['companies'][0]['href']==='https://crm.judibas.com/'
                && $portal['products'][0]['external_url']==='https://crm.judibas.com/';
        });
    }

    public function test_theme_selection_persists_and_is_limited_to_super_admin(): void
    {
        DB::table('site_content')->where('key', 'products_theme')->delete();
        $this->assertSame('classic', ProductCatalogue::theme());
        $this->postJson('/admin/api/platform-settings/products', ['theme' => 'workspace'])->assertForbidden();
        $this->withSession(['judibas_admin' => true]);
        foreach (ProductCatalogue::THEMES as $theme) {
            $this->postJson('/admin/api/platform-settings/products', ['theme' => $theme])->assertOk();
            $this->assertSame($theme, ProductCatalogue::theme());
            $this->get('/products')->assertOk()->assertViewHas('portal', fn ($portal) => $portal['productsTheme'] === $theme);
        }
        $this->postJson('/admin/api/platform-settings/products', ['theme' => 'invalid'])->assertUnprocessable();
        $this->assertSame('stacked', ProductCatalogue::theme());
    }

    public function test_company_cards_follow_employee_product_and_company_assignments(): void
    {
        $company = DB::table('communication_companies')->where('name', 'Cruxlo')->first();
        if (! $company) {
            $id = DB::table('communication_companies')->insertGetId(['name' => 'Cruxlo', 'created_at' => now(), 'updated_at' => now()]);
        } else {
            $id = $company->id;
        }
        $user = User::factory()->create(['is_active' => true, 'judibas_administrator' => false]);
        UserProductAccess::sync($user, ['product_slugs' => ['accounts'], 'company_ids' => [$id]]);
        $this->actingAs($user)->withSession(['judibas_admin' => false]);
        $this->get('/products')->assertOk()->assertViewHas('portal', function ($portal) use ($id) {
            $groups = $portal['catalogueGroups'];
            foreach ($groups as $group) {
                if ($group['slug'] !== 'accounts') {
                    return false;
                }
                foreach ($group['companies'] as $company) {
                    if ($company['name'] !== 'Cruxlo' || $company['href'] !== \App\Services\CompanyWorkspace::url('accounting',$id).'?workspace_group=services') {
                        return false;
                    }
                }
            }

            return count($groups) === 1;
        });
        $this->postJson('/admin/api/platform-settings/products', ['theme' => 'directory'])->assertForbidden();
    }

    public function test_guest_catalogue_does_not_expose_company_directory(): void
    {
        foreach (ProductCatalogue::THEMES as $theme) {
            DB::table('site_content')->updateOrInsert(['key'=>'products_theme'], ['value'=>json_encode($theme)]);
            $this->get('/products')->assertRedirect('/login')->assertSessionHas('url.intended', '/products');
            $this->get('/')->assertOk()->assertViewHas('portal', fn ($portal) => count($portal['products']) > 0 && $portal['catalogueGroups'] === [] && $portal['workspaceCompanies'] === []);
        }
    }

    public function test_employee_returns_to_catalogue_after_login_with_only_assigned_products(): void
    {
        $user=User::factory()->create(['is_active'=>true,'password'=>\Illuminate\Support\Facades\Hash::make('catalogue-test-password')]);
        UserProductAccess::sync($user,['product_slugs'=>['focus-to-do'],'company_ids'=>[]]);
        $this->get('/products?search=focus')->assertRedirect('/login');
        $this->post('/login',['email'=>$user->email,'password'=>'catalogue-test-password'])->assertRedirect('/products?search=focus');
        $this->get('/products')->assertOk()->assertViewHas('portal',fn($p)=>array_column($p['products'],'slug')===['focus-to-do'] && $p['catalogueGroups']===[]);
    }

    public function test_super_admin_returns_to_catalogue_after_session_regeneration(): void
    {
        config(['judibas.admin_email'=>'catalogue-admin@example.test','judibas.admin_password_hash'=>\Illuminate\Support\Facades\Hash::make('catalogue-test-password')]);
        $this->get('/products')->assertRedirect('/login');
        $this->post('/login',['email'=>'catalogue-admin@example.test','password'=>'catalogue-test-password'])->assertRedirect('/products')->assertSessionHas('judibas_admin',true);
        $this->get('/products')->assertOk();
    }

    public function test_inactive_users_cannot_open_the_catalogue(): void
    {
        $user=User::factory()->create(['is_active'=>false]);
        $this->actingAs($user)->get('/products')->assertForbidden();
    }

    public function test_pms_card_only_lists_assigned_companies_and_hides_unassigned_products(): void
    {
        $id = DB::table('communication_companies')->where('name', 'Skyinfinit')->value('id');
        if (! $id) {
            $id = DB::table('communication_companies')->insertGetId(['name' => 'Skyinfinit', 'created_at' => now(), 'updated_at' => now()]);
        }
        $user = User::factory()->create(['is_active' => true, 'judibas_administrator' => false]);
        UserProductAccess::sync($user, ['product_slugs' => ['projects'], 'company_ids' => [$id]]);
        $this->actingAs($user)->withSession(['judibas_admin' => false]);
        $this->get('/products')->assertOk()->assertViewHas('portal', function ($portal) use ($id) {
            $groups = $portal['catalogueGroups'];

            return count($groups) === 1 && $groups[0]['slug'] === 'projects' && $groups[0]['allHref'] === '/pms/all' && $groups[0]['companies'] === [['name' => 'Skyinfinit', 'href' => '/pms/skyinfinit']];
        });
        DB::table('user_product_access')->where('user_id', $user->id)->delete();
        $this->get('/products')->assertOk()->assertViewHas('portal', fn ($portal) => $portal['catalogueGroups'] === [] && $portal['products'] === []);
    }
}
