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
        $this->assertSame('disclosure', ProductCatalogue::theme());
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
        $this->get('/products')->assertOk()->assertViewHas('portal', function ($portal) {
            foreach ($portal['catalogueGroups'] as $group) {
                if ($group['companies']) {
                    return false;
                }
            }

            return true;
        });
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
