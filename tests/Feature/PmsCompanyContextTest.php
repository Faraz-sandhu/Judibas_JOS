<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pms\Database\Seeders\PmsFoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PmsCompanyContextTest extends TestCase
{
    use DatabaseTransactions;

    private array $a;

    private array $b;

    private int $projectA;

    private int $projectB;

    private int $taskA;

    private int $taskB;

    private int $dep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PmsFoundationSeeder::class);
        Event::fake();
        Notification::fake();
        $this->withSession(['judibas_admin' => true]);
        $this->a = $this->company('Context Alpha');
        $this->b = $this->company('Context Beta');
        $groups=config('product-catalogue.groups');foreach($groups as &$group)if($group['id']==='pms')$group['companies']=['Context Alpha','Context Beta'];unset($group);config(['product-catalogue.groups'=>$groups]);
        $this->dep = $this->department('Context Department');
        $this->projectA = $this->project($this->client($this->a[1], 'Alpha Client'), 'Alpha Project', [$this->dep]);
        $this->projectB = $this->project($this->client($this->b[1], 'Beta Client'), 'Beta Project', [$this->dep]);
        $this->taskA = $this->task($this->projectA, $this->dep, 'Alpha task');
        $this->taskB = $this->task($this->projectB, $this->dep, 'Beta task');
    }

    private function company(string $name): array
    {
        $platform = DB::table('communication_companies')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        $pms = DB::table('pms_companies')->insertGetId(['name' => $name, 'status' => true, 'platform_company_id' => $platform, 'created_at' => now(), 'updated_at' => now()]);

        return [$platform, $pms];
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

    private function employee(int $company): User
    {
        $user = User::factory()->create(['is_active' => true, 'status' => 1]);
        DB::table('user_product_access')->insert(['user_id' => $user->id, 'product_slug' => 'projects']);
        DB::table('communication_company_memberships')->insert(['user_id' => $user->id, 'company_id' => $company]);
        $role = DB::table('pms_roles')->where('role_key', 'developer')->value('id');
        DB::table('pms_role_users')->insert(['user_id' => $user->id, 'role_id' => $role]);

        return $user;
    }

    public function test_super_admin_selection_scopes_all_project_task_hierarchy_and_reporting_requests(): void
    {
        $scope = '?workspace_company='.$this->a[0];
        $this->getJson('/pms/api/clients'.$scope)->assertOk()->assertJsonCount(1, 'companies')->assertJsonFragment(['name' => 'Alpha Client'])->assertJsonMissing(['name' => 'Beta Client']);
        $this->getJson('/pms/api/hierarchy'.$scope)->assertOk()->assertJsonCount(1, 'companies')->assertJsonFragment(['name' => 'Alpha Project'])->assertJsonMissing(['name' => 'Beta Project']);
        $this->getJson('/pms/api/projects'.$scope)->assertOk()->assertJsonCount(1, 'data.projects')->assertJsonFragment(['name' => 'Alpha Project']);
        $this->getJson('/pms/api/projects/'.$this->projectB.$scope)->assertNotFound();
        $this->getJson('/pms/api/tasks/'.$this->taskB.$scope)->assertNotFound();
        $this->getJson('/pms/api/dashboard'.$scope)->assertOk()->assertJsonPath('data.ongoingProjectCount', 1);
        $this->getJson('/pms/api/reports/overview'.$scope)->assertOk()->assertJsonPath('summary.total', 1);
        $this->get('/pms'.$scope)->assertOk()->assertViewHas('portal', fn ($p) => $p['pmsBootstrap']['company_context']['name'] === 'Context Alpha');
        $this->getJson('/pms/api/projects?workspace_company=all')->assertOk()->assertJsonFragment(['name' => 'Alpha Project'])->assertJsonFragment(['name' => 'Beta Project']);
        $this->getJson('/pms/api/projects'.$scope)->assertOk()->assertJsonCount(1, 'data.projects');
    }

    public function test_header_scope_is_tab_local_and_blocks_foreign_mutations(): void
    {
        $this->withHeader('X-PMS-Company', (string) $this->a[0])->getJson('/pms/api/hierarchy')->assertOk()->assertJsonMissing(['name' => 'Beta Project']);
        $this->patchJson('/pms/api/hierarchy-board/tasks/'.$this->taskB.'/status', ['status' => 'completed'])->assertNotFound();
        $this->withHeader('X-PMS-Company', (string) $this->b[0])->getJson('/pms/api/hierarchy')->assertOk()->assertJsonFragment(['name' => 'Beta Project'])->assertJsonMissing(['name' => 'Alpha Project']);
        $this->withHeader('X-PMS-Company', 'all')->getJson('/pms/api/hierarchy')->assertOk()->assertJsonFragment(['name' => 'Alpha Project'])->assertJsonFragment(['name' => 'Beta Project']);
    }

    public function test_view_all_permission_does_not_grant_unassigned_companies(): void
    {
        $user = $this->employee($this->a[0]);
        $user->company_id = null;
        $user->save();
        DB::table('pms_role_users')->where('user_id', $user->id)->update(['role_id' => DB::table('pms_roles')->where('role_key', 'project_manager')->value('id')]);
        $this->withSession(['judibas_admin' => false])->actingAs($user);
        $this->getJson('/pms/api/hierarchy?workspace_company=all')->assertOk()->assertJsonCount(1, 'companies')->assertJsonFragment(['name' => 'Alpha Project'])->assertJsonMissing(['name' => 'Beta Project']);
        $this->getJson('/pms/api/tasks/'.$this->taskB)->assertNotFound();
        $this->getJson('/pms/api/hierarchy?workspace_company='.$this->b[0])->assertForbidden();
        $this->get('/pms?workspace_company='.$this->b[0])->assertForbidden();
        $this->getJson('/pms/api/hierarchy?workspace_company=bad')->assertUnprocessable();
    }

    public function test_employee_assignment_rules_remain_within_selected_company(): void
    {
        $user = $this->employee($this->a[0]);
        $user->company_id = null;
        $user->save();
        DB::table('pms_task_user')->insert(['user_id' => $user->id, 'task_id' => $this->taskA]);
        $this->withSession(['judibas_admin' => false])->actingAs($user);
        $this->getJson('/pms/api/hierarchy?workspace_company='.$this->a[0])->assertOk()->assertJsonPath('show_companies', false)->assertJsonFragment(['name' => 'Alpha Project'])->assertJsonMissing(['name' => 'Beta Project']);
        $this->getJson('/pms/api/tasks/'.$this->taskA.'?workspace_company='.$this->a[0])->assertOk();
        $this->getJson('/pms/api/tasks/'.$this->taskB.'?workspace_company=all')->assertNotFound();
    }

    public function test_work_logs_and_exports_keep_company_scope(): void
    {
        $user = User::factory()->create();
        foreach ([$this->taskA, $this->taskB] as $task) {
            DB::table('pms_timer_logs')->insert(['user_id' => $user->id, 'task_id' => $task, 'start_time' => now()->subHour(), 'end_time' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')->getJson('/pms/api/work-logs?workspace_company='.$this->a[0])->assertOk()->assertJsonFragment(['title' => 'Alpha task'])->assertJsonMissing(['title' => 'Beta task']);
        $response = $this->get('/pms/api/work-logs/export/csv?workspace_company='.$this->a[0])->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Alpha task',$csv);
        $this->assertStringNotContainsString('Beta task',$csv);
    }
}
