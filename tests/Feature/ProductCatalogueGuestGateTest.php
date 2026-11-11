<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProductCatalogue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductCatalogueGuestGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver'=>'array']);
        $this->withoutVite();
    }

    public function test_guest_redirect_happens_before_any_catalogue_or_company_query(): void
    {
        DB::shouldReceive('table')->never();
        foreach(ProductCatalogue::THEMES as $theme) {
            $this->get('/products?theme='.$theme)->assertRedirect('/login')->assertSessionHas('url.intended','/products?theme='.$theme);
        }
    }

    public function test_inactive_identity_is_denied_before_catalogue_queries(): void
    {
        DB::shouldReceive('table')->never();
        $user=new User;
        $user->forceFill(['id'=>999999,'is_active'=>false,'judibas_administrator'=>false]);
        $this->actingAs($user)->get('/products')->assertForbidden();
    }

    public function test_super_admin_login_keeps_the_intended_products_destination(): void
    {
        config(['judibas.admin_email'=>'catalogue-login@example.test','judibas.admin_password_hash'=>Hash::make('catalogue-login-password')]);
        DB::shouldReceive('table')->never();
        $this->get('/products')->assertRedirect('/login');
        $this->post('/login',['email'=>'catalogue-login@example.test','password'=>'catalogue-login-password'])
            ->assertRedirect('/products')->assertSessionHas('judibas_admin',true)->assertSessionMissing('url.intended');
    }

    public function test_modal_login_returns_json_and_does_not_require_a_catalogue_visit(): void
    {
        config(['judibas.admin_email'=>'modal@example.test','judibas.admin_password_hash'=>Hash::make('modal-test-password')]);
        DB::shouldReceive('table')->never();
        $this->postJson('/login',['email'=>'modal@example.test','password'=>'modal-test-password','redirect_to'=>'/products'])
            ->assertOk()->assertJsonPath('redirect','/products')->assertSessionHas('judibas_admin',true);
    }

    public function test_modal_invalid_credentials_and_external_destination_are_rejected(): void
    {
        config(['judibas.admin_email'=>'modal-failed@example.test','judibas.admin_password_hash'=>Hash::make('modal-test-password')]);
        DB::shouldReceive('table')->never();
        $this->postJson('/login',['email'=>'modal-failed@example.test','password'=>'incorrect','redirect_to'=>'/products'])
            ->assertUnprocessable()->assertJsonStructure(['message'])->assertSessionMissing('judibas_admin');
        $this->postJson('/login',['email'=>'modal-failed@example.test','password'=>'modal-test-password','redirect_to'=>'https://example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('redirect_to')->assertSessionMissing('judibas_admin');
    }
}
