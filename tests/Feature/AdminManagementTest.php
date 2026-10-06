<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): static
    {
        $this->withSession(['judibas_admin' => true]);

        return $this;
    }

    public function test_admin_api_requires_administrator(): void
    {
        $this->getJson('/admin/api')->assertForbidden();
        $this->postJson('/admin/api/blogs', ['title' => 'No access'])->assertForbidden();
        $this->deleteJson('/admin/api/users/1')->assertForbidden();
    }

    public function test_articles_can_be_created_edited_published_and_deleted(): void
    {
        $v = ['title' => 'Test update', 'slug' => 'test-admin-update', 'excerpt' => 'A test', 'body' => 'Article content', 'published' => false];
        $this->admin()->postJson('/admin/api/blogs', $v)->assertOk();
        $id = DB::table('blog_posts')->where('slug', $v['slug'])->value('id');
        $this->get('/blog/test-admin-update')->assertNotFound();
        $v['published'] = true;
        $v['title'] = 'Published update';
        $this->postJson('/admin/api/blogs/'.$id, $v)->assertOk();
        $this->get('/blog/test-admin-update')->assertOk()->assertViewHas('portal', fn ($p) => $p['page']['title'] === 'Published update');
        $this->get('/blog')->assertViewHas('portal', fn ($p) => collect($p['posts'])->contains('slug', 'test-admin-update'));
        $this->deleteJson('/admin/api/blogs/'.$id)->assertOk();
        $this->get('/blog/test-admin-update')->assertNotFound();
    }

    public function test_product_overrides_affect_catalogue_visibility_and_order(): void
    {
        $v = ['slug' => 'crm', 'name' => 'Company CRM', 'description' => 'Updated description', 'icon' => 'C', 'color' => '#123456', 'display_order' => 0, 'visible' => false];
        $this->admin()->postJson('/admin/api/products', $v)->assertOk();
        $this->get('/products/crm')->assertNotFound();
        $this->get('/')->assertViewHas('portal', fn ($p) => ! collect($p['products'])->contains('slug', 'crm'));
        $v['visible'] = true;
        $this->postJson('/admin/api/products', $v)->assertOk();
        $this->get('/products/crm')->assertViewHas('portal', fn ($p) => $p['product']['name'] === 'Company CRM' && $p['product']['icon'] === 'C');
        $v['slug'] = 'not-a-module';
        $this->postJson('/admin/api/products', $v)->assertUnprocessable();
    }

    public function test_company_pages_and_announcement_are_updated(): void
    {
        $this->admin()->postJson('/admin/api/pages', ['slug' => 'about', 'title' => 'Our company', 'intro' => 'New introduction', 'body' => 'Our company story'])->assertOk();
        $this->get('/about')->assertViewHas('portal', fn ($p) => $p['page']['title'] === 'Our company');
        $this->postJson('/admin/api/announcement', ['title' => 'News', 'text' => 'A company update', 'url' => '/blog'])->assertOk();
        $this->get('/')->assertViewHas('portal', fn ($p) => $p['announcement']['url'] === '/blog');
        $this->postJson('/admin/api/announcement', ['title' => 'News', 'text' => 'Unsafe link', 'url' => 'javascript:alert(1)'])->assertUnprocessable();
    }

    public function test_employee_access_is_assigned_and_can_be_revoked(): void
    {
        $v = ['company_id' => DB::table('communication_companies')->value('id'), 'name' => 'Test employee', 'email' => 'admin-feature-test@example.test', 'password' => 'test-password-long', 'is_active' => true, 'product_slugs' => ['crm']];
        $this->admin()->postJson('/admin/api/users', $v)->assertOk();
        $u = User::where('email', $v['email'])->firstOrFail();
        $this->assertNotSame($v['password'], $u->password);
        $this->actingAs($u)->get('/workspace/crm')->assertOk();
        $this->get('/workspace/hr')->assertForbidden();
        $v['product_slugs'] = [];
        $v['password'] = '';
        $this->postJson('/admin/api/users/'.$u->id, $v)->assertOk();
        $this->get('/workspace/crm')->assertForbidden();
        $v['is_active'] = false;
        $v['product_slugs'] = ['crm'];
        $this->postJson('/admin/api/users/'.$u->id, $v)->assertOk();
        $this->actingAs($u->fresh())->get('/workspace/crm')->assertForbidden();
    }

    public function test_billing_endpoints_are_removed(): void
    {
        $this->admin()->postJson('/admin/api/plans', ['name' => 'Obsolete'])->assertNotFound();
        $this->deleteJson('/admin/api/plans/1')->assertNotFound();
        $this->getJson('/admin/api')->assertJsonMissingPath('plans')->assertJsonMissingPath('billing_enabled');
    }

    public function test_employee_login_and_logout_and_inactive_account(): void
    {
        $user = User::factory()->create(['email' => 'login-test@example.test', 'password' => 'employee-password-123', 'is_active' => true]);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHas('error');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'employee-password-123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $user->is_active = false;
        $user->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'employee-password-123'])->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_employee_cannot_access_admin_api(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->getJson('/admin/api')->assertForbidden();
    }
}
