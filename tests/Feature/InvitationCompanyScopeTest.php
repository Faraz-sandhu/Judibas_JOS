<?php

namespace Tests\Feature;

use App\Services\ProductCatalogue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvitationCompanyScopeTest extends TestCase
{
    use DatabaseTransactions;

    private function companies(): array
    {
        $a = DB::table('communication_companies')->insertGetId(['name' => 'Invite scope Alpha']);
        $b = DB::table('communication_companies')->insertGetId(['name' => 'Invite scope Beta']);
        $other = DB::table('communication_companies')->insertGetId(['name' => 'Invite scope Other']);
        config(['product-catalogue.groups' => [
            ['slug' => 'projects', 'companies' => ['Invite scope Alpha']],
            ['slug' => 'accounts', 'companies' => ['Invite scope Beta']],
        ]]);

        return [$a, $b, $other];
    }

    public function test_grouped_products_use_catalogue_companies_and_combine_selected_products(): void
    {
        [$a, $b, $other] = $this->companies();
        $this->assertSame(['projects' => [$a], 'accounts' => [$b]], ProductCatalogue::invitationCompanyScopes());
        ProductCatalogue::validateInvitationCompanies(['product_slugs' => ['projects', 'accounts'], 'company_ids' => [$a, $b]]);
        $this->withSession(['judibas_admin' => true])->postJson('/admin/api/invitations', [
            'email' => 'scope-test@example.test', 'product_slugs' => ['projects'],
            'company_ids' => [$other], 'delivery_method' => 'manual',
        ])->assertUnprocessable()->assertJsonValidationErrors('company_ids');
    }

    public function test_universal_product_allows_all_companies_even_with_a_grouped_product(): void
    {
        [$a, $b, $other] = $this->companies();
        ProductCatalogue::validateInvitationCompanies(['product_slugs' => ['communication', 'projects'], 'company_ids' => [$other]]);
        $this->withSession(['judibas_admin' => true])->postJson('/admin/api/invitations', [
            'email' => 'universal-scope@example.test', 'product_slugs' => ['communication'],
            'company_ids' => [$other], 'delivery_method' => 'manual',
        ])->assertSuccessful();
        $this->assertDatabaseHas('platform_invitations', ['email' => 'universal-scope@example.test']);
    }

    public function test_grouped_product_cannot_fall_back_to_all_when_no_companies_are_configured(): void
    {
        [, , $other] = $this->companies();
        config(['product-catalogue.groups' => [['slug' => 'projects', 'companies' => []]]]);
        $this->assertSame(['projects' => []], ProductCatalogue::invitationCompanyScopes());
        $this->withSession(['judibas_admin' => true])->postJson('/admin/api/invitations', [
            'email' => 'empty-scope@example.test', 'product_slugs' => ['projects'],
            'company_ids' => [$other], 'delivery_method' => 'manual',
        ])->assertUnprocessable()->assertJsonValidationErrors('company_ids');
    }
}
