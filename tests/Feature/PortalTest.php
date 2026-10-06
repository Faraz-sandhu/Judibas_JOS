<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->withoutVite();
    }

    public function test_catalogue_and_product_pages(): void
    {
        $this->get('/')->assertOk()->assertViewHas('portal', fn ($p) => count($p['products']) === 16);
        $this->get('/products/crm')->assertOk()->assertViewHas('portal', fn ($p) => $p['product']['slug'] === 'crm');
        $this->get('/products/unknown')->assertNotFound();
    }

    public function test_page_data_is_rendered_as_javascript(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('window.portal = JSON.parse(', false)
            ->assertDontSee('Illuminate\\ Support', false);
    }

    public function test_settings_are_protected(): void
    {
        $this->post('/admin/settings', ['name' => 'Changed', 'tagline' => 'Changed'])->assertForbidden();
        config(['judibas.admin_email' => 'admin@gmail.com', 'judibas.admin_password_hash' => null]);
        $this->post('/admin/login', ['email' => 'admin@gmail.com', 'password' => 'invalid'])->assertSessionHas('error')->assertSessionMissing('judibas_admin');
    }

    public function test_admin_can_sign_in_save_branding_and_sign_out(): void
    {
        config(['judibas.admin_email' => 'admin@gmail.com', 'judibas.admin_password_hash' => Hash::make('test-password-123')]);
        $this->post('/login', ['email' => 'admin@gmail.com', 'password' => 'test-password-123'])->assertRedirect('/admin')->assertSessionHas('judibas_admin', true);
        $this->post('/admin/settings', ['name' => 'New company', 'tagline' => 'A new headline', 'logo' => UploadedFile::fake()->image('logo.png')])->assertSessionHas('success');
        $this->get('/')->assertViewHas('portal', fn ($p) => $p['name'] === 'New company' && str_starts_with($p['logo'], '/branding/'));
        $this->post('/admin/logout')->assertRedirect('/')->assertSessionMissing('judibas_admin');
        $this->post('/admin/settings', ['name' => 'Unauthorized', 'tagline' => 'Changed'])->assertForbidden();
    }

    public function test_shared_login_requires_admin_email_and_password(): void
    {
        config(['judibas.admin_email' => 'admin@gmail.com', 'judibas.admin_password_hash' => Hash::make('password')]);
        $this->get('/admin')->assertRedirect('/login');
        $this->post('/login', ['email' => 'admin@gmail.com', 'password' => 'incorrect'])->assertSessionMissing('judibas_admin');
        $this->post('/login', ['email' => 'other@example.test', 'password' => 'password'])->assertSessionMissing('judibas_admin');
        $this->post('/admin/login', ['password' => 'password'])->assertSessionHasErrors('email')->assertSessionMissing('judibas_admin');
        $this->post('/login', ['email' => 'admin@gmail.com', 'password' => 'password'])->assertRedirect('/admin')->assertSessionHas('judibas_admin', true);
        $this->get('/login')->assertRedirect('/admin');
        $this->get('/admin/api')->assertOk();
        $this->post('/logout')->assertRedirect('/')->assertSessionMissing('judibas_admin');
        $this->get('/admin/api')->assertForbidden();
    }

    public function test_invalid_logo_is_rejected(): void
    {
        $this->withSession(['judibas_admin' => true])->post('/admin/settings', ['name' => 'Judibas', 'tagline' => 'Hello', 'logo' => UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('logo');
    }
}
