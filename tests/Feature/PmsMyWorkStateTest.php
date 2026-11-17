<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmsMyWorkStateTest extends TestCase
{
    use DatabaseTransactions;

    private function employee(): User
    {
        $user = User::factory()->create(['is_active' => true, 'status' => 1]);
        DB::table('user_product_access')->insert(['user_id' => $user->id, 'product_slug' => 'projects']);
        $role = DB::table('pms_roles')->insertGetId(['role_name' => 'My work regression', 'role_key' => 'mywork_regression']);
        $permission = DB::table('pms_permissions')->where('permission_key', 'my-work-view')->value('id');
        $this->assertNotNull($permission);
        DB::table('pms_role_users')->insert(['role_id' => $role, 'user_id' => $user->id]);
        DB::table('pms_permission_roles')->insert(['role_id' => $role, 'permission_id' => $permission]);
        $this->actingAs($user);

        return $user;
    }

    private function work(?User $assigned, string $name, bool $active = true): int
    {
        $workflow = DB::table('pms_workflows')->insertGetId(['name' => $name, 'is_active' => $active, 'transition_mode' => 'any']);
        $column = DB::table('pms_workflow_columns')->insertGetId(['workflow_id' => $workflow, 'name' => 'To do', 'color' => 'secondary', 'position' => 0, 'is_initial' => true]);
        DB::table('pms_workflow_columns')->insert(['workflow_id' => $workflow, 'name' => 'Done', 'color' => 'success', 'position' => 1, 'is_completed' => true]);
        $task = DB::table('pms_tasks')->insertGetId(['title' => $name.' task', 'workflow_id' => $workflow, 'workflow_column_id' => $column, 'status' => 'pending']);
        if ($assigned) {
            DB::table('pms_task_user')->insert(['task_id' => $task, 'user_id' => $assigned->id]);
        }

        return $workflow;
    }

    public function test_employee_without_assigned_work_gets_an_empty_workflow_state(): void
    {
        $this->employee();
        $this->getJson('/pms/api/my-work')->assertOk()->assertJsonCount(0, 'data.tasks')
            ->assertJsonCount(0, 'data.workflows')->assertJsonPath('data.selectedWorkflow', null);
    }

    public function test_visible_backlog_task_resolves_workflow_without_a_project_link(): void
    {
        $user = $this->employee();
        $workflow = $this->work($user, 'Assigned backlog');
        $hidden = $this->work(null, 'Unassigned private workflow');
        $response = $this->getJson('/pms/api/my-work')->assertOk()->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.selectedWorkflow.id', $workflow)->assertJsonCount(2, 'data.selectedWorkflow.columns');
        $this->assertNotContains($hidden, $response->json('data.workflows.*.id'));
    }

    public function test_unavailable_workflow_filter_falls_back_to_visible_workflow(): void
    {
        $user = $this->employee();
        $workflow = $this->work($user, 'Visible workflow');
        $hidden = $this->work(null, 'Hidden workflow');
        $this->getJson('/pms/api/my-work?workflow='.$hidden)->assertOk()
            ->assertJsonPath('data.selectedWorkflow.id', $workflow)->assertJsonCount(1, 'data.tasks');
    }

    public function test_inactive_workflow_does_not_hide_the_existence_of_assigned_work(): void
    {
        $user = $this->employee();
        $this->work($user, 'Inactive assigned workflow', false);
        $this->getJson('/pms/api/my-work')->assertOk()->assertJsonCount(1, 'data.tasks')
            ->assertJsonCount(0, 'data.workflows')->assertJsonPath('data.selectedWorkflow', null);
    }

    public function test_multiple_assigned_workflows_can_be_selected_without_mixing_columns(): void
    {
        $user = $this->employee();
        $first = $this->work($user, 'First workflow');
        $second = $this->work($user, 'Second workflow');
        $response = $this->getJson('/pms/api/my-work?workflow='.$second)->assertOk()
            ->assertJsonCount(2, 'data.workflows')->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.selectedWorkflow.id', $second)
            ->assertJsonPath('data.tasks.0.workflow_id', $second);
        $this->assertContains($first, $response->json('data.workflows.*.id'));
    }
}
