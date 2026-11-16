<?php

namespace Tests\Feature;

use App\Modules\Pms\Models\Workflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PmsWorkflowCrudTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->withSession(['judibas_admin' => true]);
    }

    private function payload(): array
    {
        return ['name' => 'Workflow regression fixture', 'department_id' => null, 'is_active' => true,
            'transition_mode' => 'any', 'initial_column' => 0, 'completed_column' => 1,
            'columns' => [['name' => 'To Do', 'color' => 'secondary'], ['name' => 'Done', 'color' => 'success']]];
    }

    private function createWorkflow(): Workflow
    {
        $this->postJson('/pms/api/workflows', $this->payload())->assertOk();

        return Workflow::where('name', 'Workflow regression fixture')->latest('id')->firstOrFail();
    }

    public function test_create_list_edit_and_delete_workflow(): void
    {
        $w = $this->createWorkflow();
        $this->assertCount(2, $w->columns);
        $this->assertTrue($w->columns[0]->is_initial);
        $this->assertTrue($w->columns[1]->is_completed);
        $this->getJson('/pms/api/workflows')->assertOk()->assertJsonFragment(['name' => $w->name]);
        $payload = $this->payload();
        $payload['columns'] = $w->columns->map->only(['id', 'name', 'color'])->all();
        $payload['name'] = 'Updated workflow fixture';
        $payload['columns'][] = ['name' => 'Review', 'color' => 'warning'];
        $this->putJson('/pms/api/workflows/'.$w->id, $payload)->assertOk();
        $this->assertCount(3, $w->fresh()->columns);
        array_pop($payload['columns']);
        $this->putJson('/pms/api/workflows/'.$w->id, $payload)->assertOk();
        $this->assertCount(2, $w->fresh()->columns);
        $this->deleteJson('/pms/api/workflows/'.$w->id)->assertOk();
        $this->assertDatabaseMissing('pms_workflows', ['id' => $w->id]);
    }

    public function test_used_columns_cannot_be_removed_and_changes_roll_back(): void
    {
        $w = $this->createWorkflow();
        $column = $w->columns->first();
        $task = DB::table('pms_tasks')->insertGetId(['title' => 'Retain workflow task', 'status' => 'pending', 'workflow_id' => $w->id, 'workflow_column_id' => $column->id, 'created_at' => now(), 'updated_at' => now()]);
        $payload = $this->payload();
        $payload['name'] = 'Should roll back';
        $this->putJson('/pms/api/workflows/'.$w->id, $payload)->assertUnprocessable();
        $this->assertSame($w->name, $w->fresh()->name);
        $this->assertSame(2, $w->columns()->count());
        $this->assertDatabaseHas('pms_tasks', ['id' => $task, 'workflow_column_id' => $column->id]);
        $this->deleteJson('/pms/api/workflows/'.$w->id)->assertUnprocessable();
    }

    public function test_invalid_stage_indexes_and_duplicate_column_ids_are_validation_errors(): void
    {
        $payload = $this->payload();
        $payload['initial_column'] = 4;
        $this->postJson('/pms/api/workflows', $payload)->assertUnprocessable()->assertJsonValidationErrors('initial_column');
        $payload['initial_column'] = 1;
        $this->postJson('/pms/api/workflows', $payload)->assertUnprocessable()->assertJsonValidationErrors('completed_column');
        $w = $this->createWorkflow();
        $payload = $this->payload();
        $payload['columns'][0]['id'] = $w->columns[0]->id;
        $payload['columns'][1]['id'] = $w->columns[0]->id;
        $this->putJson('/pms/api/workflows/'.$w->id, $payload)->assertUnprocessable();
    }

    public function test_columns_from_another_workflow_cannot_be_submitted(): void
    {
        $a = $this->createWorkflow();
        $b = $this->createWorkflow();
        $payload = $this->payload();
        $payload['columns'][0]['id'] = $a->columns[0]->id;
        $this->postJson('/pms/api/workflows', $payload)->assertUnprocessable();
        $this->putJson('/pms/api/workflows/'.$b->id, $payload)->assertUnprocessable();
    }

    public function test_account_without_workflow_permission_cannot_create_or_edit(): void
    {
        $w = $this->createWorkflow();
        $user = \App\Models\User::factory()->create(['is_active' => true, 'status' => 1]);
        DB::table('user_product_access')->insert(['user_id' => $user->id, 'product_slug' => 'projects']);
        $this->withSession(['judibas_admin' => false])->actingAs($user);
        $this->postJson('/pms/api/workflows', $this->payload())->assertForbidden();
        $this->putJson('/pms/api/workflows/'.$w->id, $this->payload())->assertForbidden();
        $this->deleteJson('/pms/api/workflows/'.$w->id)->assertForbidden();
    }
}
