<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pms\Database\Seeders\PmsFoundationSeeder;
use App\Modules\Pms\Services\PmsVisibility;
use App\Services\UserProductAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogueAllScopeTest extends TestCase
{
    use DatabaseTransactions;

    private array $ids = [];

    private array $journals = [];

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Notification::fake();
        $this->withSession(['judibas_admin' => true]);
        $groups = config('product-catalogue.groups');
        foreach ($groups as &$group) {
            if ($group['slug'] === 'accounts') {
                $group['companies'] = ['Scope Accounting '.$group['id']];
            }
        }unset($group);
        config(['product-catalogue.groups' => $groups]);
        foreach (['services' => 100, 'ecommerce' => 200, 'physical' => 300, 'unlisted' => 9000] as $key => $amount) {
            $id = DB::table('communication_companies')->insertGetId(['name' => 'Scope Accounting '.$key, 'created_at' => now(), 'updated_at' => now()]);
            $this->ids[$key] = $id;
            $this->postJson('/accounting/api/initialize', ['company_id' => $id])->assertOk();
            $this->postJson('/accounting/api/books', ['company_id' => $id, 'version' => 0, 'currency' => 'PKR', 'start_date' => now()->startOfMonth()->toDateString(), 'approval_required' => false])->assertOk();
            $accounts = DB::table('accounting_accounts')->where('company_id', $id)->pluck('id', 'code');
            $journal = $this->postJson('/accounting/api/journals', ['company_id' => $id, 'request_key' => (string) Str::uuid(), 'kind' => 'general', 'posting_date' => now()->toDateString(), 'lines' => [['account_id' => $accounts['01-01-01'], 'debit' => (string) $amount, 'credit' => '0'], ['account_id' => $accounts['03-01'], 'debit' => '0', 'credit' => (string) $amount]]])->assertOk()->json('journal');
            $this->journals[$key] = $journal['id'];
            $this->postJson('/accounting/api/journals/'.$journal['id'].'/post', ['company_id' => $id, 'version' => 1])->assertOk();
        }
    }

    private function all(string $section, ?string $group = null): string
    {
        return '/accounting/api/all-companies?'.http_build_query(['section' => $section, 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()] + ($group === null ? [] : ['workspace_group' => $group]));
    }

    public function test_each_accounting_all_action_limits_records_companies_and_totals_to_its_card(): void
    {
        foreach (['services' => '100.00', 'ecommerce' => '200.00', 'physical' => '300.00'] as $group => $cash) {
            $this->getJson('/accounting/api/data?company_id=all&workspace_group='.$group)->assertOk()->assertJsonCount(1, 'companies')->assertJsonPath('companies.0.id', $this->ids[$group]);
            $this->getJson($this->all('overview', $group))->assertOk()->assertJsonPath('company_count', 1)->assertJsonPath('totals.0.summary.cash_bank', $cash);
            $records = $this->getJson($this->all('journals', $group))->assertOk()->assertJsonPath('records.total', 1)->json('records.data');
            $this->assertSame($this->journals[$group], $records[0]['id']);
            $this->assertStringContainsString('workspace_group='.$group, $records[0]['url']);
            $chart = $this->getJson($this->all('chart', $group))->assertOk()->json('records.data');
            foreach ($chart as $row) {
                foreach ($row['contributors'] as $contributor) {
                    $this->assertSame($this->ids[$group], $contributor['company_id']);
                    $this->assertStringContainsString('workspace_group='.$group, $contributor['url']);
                }
            }
        }
    }

    public function test_generic_accounting_all_is_union_of_accounting_cards_and_excludes_other_products(): void
    {
        $this->getJson($this->all('overview'))->assertOk()->assertJsonPath('company_count', 3)->assertJsonPath('totals.0.summary.cash_bank', '600.00');
        $rows = $this->getJson($this->all('journals'))->assertOk()->assertJsonPath('records.total', 3)->json('records.data');
        $this->assertNotContains($this->ids['unlisted'], array_column($rows, 'company_id'));
        $this->getJson($this->all('overview', 'pms'))->assertUnprocessable();
        $this->getJson('/accounting/api/data?company_id='.$this->ids['ecommerce'].'&workspace_group=services')->assertForbidden();
    }

    private function client(int $company, string $name): int
    {
        return $this->postJson('/pms/api/clients', ['company_id' => $company, 'name' => $name, 'status' => true])->assertCreated()->json('client.id');
    }

    private function department(string $name): int
    {
        return DB::table('pms_departments')->insertGetId(['dept_name' => $name, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function project(int $client, string $name, array $departments): int
    {
        $workflow = DB::table('pms_workflows')->where('is_default', true)->value('id');

        return $this->postJson('/pms/api/projects', ['client_id' => $client, 'name' => $name, 'start_date' => '2026-10-12', 'department_ids' => $departments, 'workflow_ids' => [$workflow]])->assertCreated()->json('project_id');
    }

    private function task(int $project, int $department, string $title, ?int $sprint = null): int
    {
        $w = DB::table('pms_project_workflow')->where('project_id', $project)->value('workflow_id');
        $col = DB::table('pms_workflow_columns')->where('workflow_id', $w)->orderBy('position')->value('id');

        return $this->postJson('/pms/api/tasks', ['title' => $title, 'project_id' => $project, 'department_id' => $department, 'workflow_id' => $w, 'workflow_column_id' => $col, 'status' => 'pending', 'sprint_id' => $sprint])->assertOk()->json('task.id');
    }

    public function test_pms_all_only_includes_its_listed_companies_and_intersects_employee_access(): void
    {
        $this->seed(PmsFoundationSeeder::class);
        $platform = [];
        foreach (['Skyinfinit', 'Cruxlo', 'Wecoma', 'Bedizen'] as $name) {
            $platform[$name] = DB::table('communication_companies')->where('name', $name)->value('id') ?? DB::table('communication_companies')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }
        PmsVisibility::syncCompanies();
        $pms = DB::table('pms_companies')->whereIn('platform_company_id', array_values($platform))->pluck('id', 'name');
        $dep = $this->department('Catalogue scope department');
        $sky = $this->project($this->client($pms['Skyinfinit'], 'Catalogue Sky Client'), 'Catalogue Sky Project', [$dep]);
        $bed = $this->project($this->client($pms['Bedizen'], 'Catalogue Bed Client'), 'Catalogue Bed Project', [$dep]);
        $mine = $this->task($sky, $dep, 'Catalogue Sky task');
        $outside = $this->task($bed, $dep, 'Catalogue Bed task');
        $response = $this->getJson('/pms/api/hierarchy?workspace_company=all')->assertOk()->assertJsonFragment(['name' => 'Catalogue Sky Project'])->assertJsonMissing(['name' => 'Catalogue Bed Project']);
        $this->assertSame(['Cruxlo', 'Skyinfinit', 'Wecoma'], array_column($response->json('companies'), 'name'));
        $this->getJson('/pms/api/tasks/'.$outside.'?workspace_company=all')->assertNotFound();
        $user = User::factory()->create(['is_active' => true, 'status' => 1, 'judibas_administrator' => false]);
        UserProductAccess::sync($user, ['product_slugs' => ['projects'], 'company_ids' => [$platform['Skyinfinit'], $platform['Bedizen']]]);
        DB::table('pms_role_users')->where('user_id', $user->id)->delete();
        DB::table('pms_role_users')->insert(['user_id' => $user->id, 'role_id' => DB::table('pms_roles')->where('role_key', 'project_manager')->value('id')]);
        $this->withSession(['judibas_admin' => false])->actingAs($user);
        $this->getJson('/pms/api/clients?workspace_company=all')->assertOk()->assertJsonCount(1,'companies')->assertJsonPath('companies.0.name','Skyinfinit');
    }
}
