<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserProductAccess;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountingChartTest extends TestCase
{
    use DatabaseTransactions;

    private int $company;

    private int $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        $this->company = DB::table('communication_companies')->insertGetId(['name' => 'Accounting test A', 'created_at' => now(), 'updated_at' => now()]);
        $this->other = DB::table('communication_companies')->insertGetId(['name' => 'Accounting test B', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function member(string $role = 'accountant'): User
    {
        $u = User::factory()->create(['is_active' => true]);
        UserProductAccess::sync($u, ['product_slugs' => ['accounts'], 'company_ids' => [$this->company], 'accounting_role' => $role]);

        return $u;
    }

    private function setupChart(): void
    {
        $this->postJson('/accounting/api/initialize', ['company_id' => $this->company, 'version' => 1])->assertOk();
    }

    private function root(string $type = 'Asset'): object
    {
        return DB::table('accounting_accounts')->where('company_id', $this->company)->where('root_type', $type)->where('is_system', true)->first();
    }

    private function payload(array $override = []): array
    {
        return array_replace(['version' => isset($override['id']) ? DB::table('accounting_accounts')->where('id', $override['id'])->value('version') : 1, 'company_id' => $this->company, 'parent_id' => $this->root()->id, 'root_type' => 'Asset', 'code' => '1190', 'name' => 'Petty Cash', 'account_type' => 'Cash', 'is_group' => false, 'active' => true, 'description' => 'Office petty cash'], $override);
    }

    public function test_product_access_and_company_scope(): void
    {
        $this->get('/accounting')->assertRedirect('/login');
        $outsider = User::factory()->create(['is_active' => true]);
        $this->actingAs($outsider)->getJson('/accounting/api/data')->assertForbidden();
        $u = $this->member();
        $this->actingAs($u)->get('/accounting')->assertOk()->assertViewHas('portal', fn ($p) => $p['product']['name'] === 'Accounting');
        $this->get('/products/accounts')->assertRedirect('/accounting');
        $this->getJson('/accounting/api/data')->assertOk()->assertJsonPath('companies.0.id', $this->company)->assertJsonCount(1, 'companies');
        $this->getJson('/accounting/api/data?company_id='.$this->other)->assertForbidden();
        $this->postJson('/accounting/api/initialize', ['company_id' => $this->other])->assertForbidden();
        $u->is_active = false;
        $u->save();
        $this->getJson('/accounting/api/data')->assertForbidden();
    }

    public function test_initialization_is_explicit_and_duplicate_safe(): void
    {
        $this->actingAs($this->member());
        $this->getJson('/accounting/api/data')->assertJsonCount(0, 'accounts');
        $this->setupChart();
        $count = DB::table('accounting_accounts')->where('company_id', $this->company)->count();
        $this->assertSame(5, DB::table('accounting_accounts')->where('company_id', $this->company)->where('is_system', true)->count());
        $this->assertGreaterThan(5, $count);
        $this->postJson('/accounting/api/initialize', ['company_id' => $this->company, 'version' => 1])->assertUnprocessable();
        $this->assertSame($count, DB::table('accounting_accounts')->where('company_id', $this->company)->count());
        $this->assertDatabaseHas('accounting_audits', ['company_id' => $this->company, 'action' => 'Chart initialized']);
    }

    public function test_create_edit_and_hierarchy_rules(): void
    {
        $this->actingAs($this->member());
        $this->setupChart();
        $a = $this->postJson('/accounting/api/accounts', $this->payload())->assertOk()->json('account');
        $this->postJson('/accounting/api/accounts', $this->payload())->assertUnprocessable();
        $this->postJson('/accounting/api/accounts', $this->payload(['id' => $a['id'], 'name' => 'Petty Cash Updated']))->assertOk();
        $this->postJson('/accounting/api/accounts', $this->payload(['code' => '1191', 'parent_id' => $a['id']]))->assertUnprocessable();
        $this->postJson('/accounting/api/accounts', $this->payload(['code' => '1191', 'parent_id' => $this->root('Expense')->id]))->assertUnprocessable();
        $this->postJson('/accounting/api/accounts', $this->payload(['id' => $a['id'], 'is_group' => true, 'account_type' => 'General']))->assertUnprocessable();
        $this->postJson('/accounting/api/accounts', $this->payload(['id' => $this->root()->id]))->assertUnprocessable();
        $this->postJson('/accounting/api/accounts', $this->payload(['code' => '1191', 'account_type' => 'Payable']))->assertUnprocessable();
        $this->postJson('/accounting/api/accounts', $this->payload(['code' => '1191', 'name' => '   ']))->assertUnprocessable();
    }

    public function test_cycle_and_active_parent_protection(): void
    {
        $this->actingAs($this->member());
        $this->setupChart();
        $group = $this->postJson('/accounting/api/accounts', $this->payload(['code' => '1200', 'name' => 'Test Group', 'is_group' => true, 'account_type' => 'General']))->assertOk()->json('account');
        $child = $this->postJson('/accounting/api/accounts', $this->payload(['code' => '1210', 'name' => 'Child Group', 'parent_id' => $group['id'], 'is_group' => true, 'account_type' => 'General']))->assertOk()->json('account');
        $this->postJson('/accounting/api/accounts', array_replace($group, ['parent_id' => $child['id']]))->assertUnprocessable();
        $this->postJson('/accounting/api/accounts', array_replace($group, ['parent_id' => $group['id']]))->assertUnprocessable();
        $this->postJson('/accounting/api/accounts', array_replace($group, ['active' => false]))->assertUnprocessable();
        $this->postJson('/accounting/api/accounts', array_replace($child, ['active' => false]))->assertOk();
        $this->postJson('/accounting/api/accounts', array_replace($group, ['active' => false]))->assertOk();
        $this->postJson('/accounting/api/accounts', $this->payload(['code' => '1220', 'parent_id' => $group['id']]))->assertUnprocessable();
    }

    public function test_viewer_cannot_write_and_accountant_cannot_delete(): void
    {
        $a = $this->member();
        $this->actingAs($a);
        $this->setupChart();
        $leaf = $this->postJson('/accounting/api/accounts', $this->payload())->assertOk()->json('account.id');
        $this->deleteJson('/accounting/api/accounts/'.$leaf, ['company_id' => $this->company, 'version' => 1])->assertForbidden();
        $this->actingAs($this->member('viewer'));
        $this->getJson('/accounting/api/data')->assertOk()->assertJsonPath('can_manage', false);
        $this->postJson('/accounting/api/accounts', $this->payload())->assertForbidden();
        $this->postJson('/accounting/api/initialize', ['company_id' => $this->company, 'version' => 1])->assertForbidden();
        $this->deleteJson('/accounting/api/accounts/'.$leaf, ['company_id' => $this->company, 'version' => 1])->assertForbidden();
    }

    public function test_manager_delete_and_cross_company_account_protection(): void
    {
        $this->actingAs($this->member('manager'));
        $this->setupChart();
        $leaf = $this->postJson('/accounting/api/accounts', $this->payload())->assertOk()->json('account.id');
        $this->deleteJson('/accounting/api/accounts/'.$this->root()->id, ['company_id' => $this->company, 'version' => 1])->assertUnprocessable();
        $group = DB::table('accounting_accounts')->where('company_id', $this->company)->where('code', '1100')->first();
        $this->deleteJson('/accounting/api/accounts/'.$group->id, ['company_id' => $this->company, 'version' => 1])->assertUnprocessable();
        $foreign = DB::table('accounting_accounts')->insertGetId(['company_id' => $this->other, 'code' => '1000', 'name' => 'Other Assets', 'root_type' => 'Asset', 'account_type' => 'General', 'is_group' => true, 'is_system' => true, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/accounting/api/accounts', $this->payload(['id' => $foreign]))->assertNotFound();
        $this->postJson('/accounting/api/accounts', $this->payload(['parent_id' => $foreign, 'code' => '1192']))->assertUnprocessable();
        $this->deleteJson('/accounting/api/accounts/'.$foreign, ['company_id' => $this->company, 'version' => 1])->assertNotFound();
        $this->deleteJson('/accounting/api/accounts/'.$leaf, ['company_id' => $this->company, 'version' => 1])->assertOk();
        $this->assertDatabaseMissing('accounting_accounts', ['id' => $leaf]);
    }

    public function test_central_provisioning_invitation_and_access_removal(): void
    {
        $this->withSession(['judibas_admin' => true]);
        $this->postJson('/admin/api/users', ['name' => 'Accounting viewer', 'email' => 'accounting-provision@example.test', 'password' => 'TestPassword123!', 'is_active' => true, 'product_slugs' => ['accounts'], 'company_ids' => [$this->company], 'accounting_role' => 'viewer'])->assertOk();
        $id = User::where('email', 'accounting-provision@example.test')->value('id');
        $this->assertDatabaseHas('accounting_members', ['user_id' => $id, 'role' => 'viewer']);
        $this->getJson('/admin/api')->assertOk();
        $this->postJson('/admin/api/users', ['name' => 'Invalid', 'email' => 'accounting-invalid@example.test', 'password' => 'TestPassword123!', 'is_active' => true, 'product_slugs' => ['accounts'], 'company_ids' => []])->assertUnprocessable();
        $invite = $this->postJson('/admin/api/invitations', ['name' => 'Invited accountant', 'email' => 'invited-accounting@example.test', 'delivery_method' => 'manual', 'product_slugs' => ['accounts'], 'company_ids' => [$this->company], 'accounting_role' => 'accountant']);
        $invite->assertCreated();
        $token = basename($invite->json('invitation_url'));
        $this->withSession(['judibas_admin' => false])->postJson('/invitations/'.$token, ['name' => 'Invited accountant', 'password' => 'TestPassword123!', 'password_confirmation' => 'TestPassword123!'])->assertOk();
        $invited = User::where('email', 'invited-accounting@example.test')->firstOrFail();
        $this->assertDatabaseHas('accounting_members', ['user_id' => $invited->id, 'role' => 'accountant']);
        $this->assertDatabaseHas('user_product_access', ['user_id' => $invited->id, 'product_slug' => 'accounts']);
        $u = User::findOrFail($id);
        DB::table('user_product_access')->where('user_id', $id)->where('product_slug', 'accounts')->delete();
        $this->withSession(['judibas_admin' => false])->actingAs($u)->getJson('/accounting/api/data')->assertForbidden();
    }

    public function test_stale_edit_and_delete_never_overwrite_newer_changes(): void
    {
        $this->actingAs($this->member('manager'));
        $this->setupChart();
        $account = $this->postJson('/accounting/api/accounts', $this->payload())->assertOk()->json('account');
        $this->postJson('/accounting/api/accounts', array_replace($account, ['name' => 'First editor saved']))->assertOk()->assertJsonPath('account.version', 2);
        $this->postJson('/accounting/api/accounts', array_replace($account, ['name' => 'Stale editor']))->assertConflict();
        $this->deleteJson('/accounting/api/accounts/'.$account['id'], ['company_id' => $this->company, 'version' => 1])->assertConflict();
        $this->assertDatabaseHas('accounting_accounts', ['id' => $account['id'], 'name' => 'First editor saved', 'version' => 2]);
        $this->postJson('/accounting/api/accounts', $this->payload(['id' => $account['id'], 'version' => null]))->assertUnprocessable();
        $this->postJson('/accounting/api/accounts', $this->payload(['id' => $account['id'], 'version' => 2, 'name' => 'Refreshed editor']))->assertOk();
    }

    public function test_database_rejects_invalid_roots_and_cross_company_parent(): void
    {
        $this->actingAs($this->member());
        $this->setupChart();
        $base = ['company_id' => $this->other, 'parent_id' => $this->root()->id, 'code' => '1190', 'name' => 'Invalid account', 'root_type' => 'Asset', 'account_type' => 'General', 'is_group' => false, 'is_system' => false, 'active' => true, 'created_at' => now(), 'updated_at' => now()];
        foreach ([$base, array_replace($base, ['company_id' => $this->company, 'parent_id' => null]), array_replace($base, ['company_id' => $this->company, 'code' => 'lowercase']), array_replace($base, ['company_id' => $this->company, 'root_type' => 'Invalid'])] as $invalid) {
            try {
                DB::transaction(fn () => DB::table('accounting_accounts')->insert($invalid));
                $this->fail('The database accepted an invalid accounting structure.');
            } catch (QueryException $exception) {
                $this->assertContains($exception->getCode(), ['23503', '23514']);
            }
        }
    }

    public function test_removing_company_membership_blocks_all_chart_actions(): void
    {
        $user = $this->member('manager');
        $this->actingAs($user);
        $this->setupChart();
        $payload = $this->payload();
        UserProductAccess::sync($user, ['product_slugs' => ['accounts'], 'company_ids' => [$this->other], 'accounting_role' => 'manager']);
        $this->getJson('/accounting/api/data?company_id='.$this->company)->assertForbidden();
        $this->postJson('/accounting/api/accounts', $payload)->assertForbidden();
        $this->getJson('/accounting/api/data')->assertOk()->assertJsonCount(1, 'companies')->assertJsonPath('companies.0.id', $this->other)->assertJsonCount(0, 'accounts');
    }
}
