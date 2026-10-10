<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class PmsDeliveryTest extends TestCase {
 use DatabaseTransactions;
 protected function setUp():void {parent::setUp();$this->seed(\App\Modules\Pms\Database\Seeders\PmsFoundationSeeder::class);}
 public function test_project_creation_preserves_normalized_name_dates_and_workflow_links():void {
  $this->withSession(['judibas_admin'=>true]);$workflow=DB::table('pms_workflows')->where('is_default',true)->value('id');
  $payload=['name'=>' Parity  Project ','company_name'=>'Parity Client','start_date'=>'2026-10-09','end_date'=>'2026-10-20','workflow_ids'=>[$workflow],'department_ids'=>[]];
  $id=$this->postJson('/pms/api/projects',$payload)->assertCreated()->json('project_id');
  $this->assertDatabaseHas('pms_projects',['id'=>$id,'name'=>'Parity Project','name_key'=>'parity project','approval'=>'pending']);
  $this->assertDatabaseHas('pms_project_workflow',['project_id'=>$id,'workflow_id'=>$workflow]);
  $this->getJson('/pms/api/projects')->assertOk();$this->getJson('/pms/api/projects/'.$id)->assertOk();
  $columns=DB::table('pms_workflow_columns')->where('workflow_id',$workflow)->orderBy('position')->get();
  $task=$this->postJson('/pms/api/tasks',['title'=>'Parity Task','project_id'=>$id,'workflow_id'=>$workflow,'workflow_column_id'=>$columns[0]->id,'status'=>'pending'])->assertOk()->json('task.id');
  $this->patchJson('/pms/api/tasks/'.$task.'/board',['workflow_id'=>$workflow,'workflow_column_id'=>$columns[2]->id])->assertOk();
  $this->assertDatabaseHas('pms_tasks',['id'=>$task,'status'=>'in_review']);
  $this->getJson('/pms/api/tasks/'.$task)->assertOk();
  $subtask=$this->postJson('/pms/api/subtasks',['task_id'=>$task,'title'=>'Subtask parity'])->assertOk()->json('subtask.id');
  $this->getJson('/pms/api/subtasks/'.$subtask)->assertOk()->assertJsonPath('subtask.title','Subtask parity');
  $this->putJson('/pms/api/subtasks/'.$subtask,['title'=>'Updated subtask parity'])->assertOk();
  $this->deleteJson('/pms/api/subtasks/'.$subtask)->assertOk();
  $this->assertDatabaseMissing('pms_subtasks',['id'=>$subtask]);
  DB::table('pms_workflows')->where('id',$workflow)->update(['transition_mode'=>'adjacent']);
  $this->patchJson('/pms/api/tasks/'.$task.'/board',['workflow_id'=>$workflow,'workflow_column_id'=>$columns[0]->id])->assertUnprocessable();
  $sprint=$this->postJson('/pms/api/projects/'.$id.'/sprints',['name'=>'Parity Sprint','status'=>'active'])->assertCreated()->json('sprint.id');
  DB::table('pms_tasks')->where('id',$task)->update(['sprint_id'=>$sprint]);
  $this->deleteJson('/pms/api/sprints/'.$sprint)->assertOk();
  $this->assertDatabaseHas('pms_tasks',['id'=>$task,'sprint_id'=>null]);
  $this->postJson('/pms/api/projects/'.$id.'/approval',['status'=>'approved'])->assertOk();
  $this->assertDatabaseHas('pms_projects',['id'=>$id,'approval'=>'approved']);
  $payload['name']='PARITY PROJECT';$this->postJson('/pms/api/projects',$payload)->assertUnprocessable();
  $payload['name']='Other';$payload['end_date']='2026-10-08';$this->postJson('/pms/api/projects',$payload)->assertUnprocessable();
  $this->getJson('/pms/api/projects/'.$id.'/edit')->assertOk()->assertJsonFragment(['workflow_ids'=>[$workflow]]);
  $payload['name']='Updated Parity';$payload['end_date']='2026-10-20';
  $this->postJson('/pms/api/projects/'.$id,$payload)->assertOk();
  $this->assertDatabaseHas('pms_projects',['id'=>$id,'name'=>'Updated Parity','name_key'=>'updated parity']);
  $this->postJson('/pms/api/project-status',['id'=>$id,'status'=>'delivered'])->assertOk();
  $this->assertDatabaseHas('pms_projects',['id'=>$id,'status'=>'delivered']);
  $this->deleteJson('/pms/api/projects/'.$id)->assertOk();
  $this->assertDatabaseMissing('pms_projects',['id'=>$id]);

 }

 public function test_employee_onboarding_preserves_roles_departments_and_isolates_product_access():void {
  $this->withSession(['judibas_admin'=>true]);
  $department=DB::table('pms_departments')->insertGetId(['dept_name'=>'Employee Parity Department','status'=>1,'created_at'=>now(),'updated_at'=>now()]);
  $role=DB::table('pms_roles')->where('role_key','developer')->value('id');
  $payload=['name'=>'Parity Employee','surname'=>'Tester','designation'=>'Developer','email'=>'pms-parity@example.test','password'=>'password123','status'=>1,'dept_id'=>$department,'role_id'=>$role];
  $this->postJson('/pms/api/employees',$payload)->assertOk();
  $user=\App\Models\User::where('email',$payload['email'])->firstOrFail();
  $this->assertDatabaseHas('pms_role_users',['user_id'=>$user->id,'role_id'=>$role]);
  $this->assertDatabaseHas('pms_user_departments',['user_id'=>$user->id,'dept_id'=>$department]);
  $this->assertDatabaseHas('user_product_access',['user_id'=>$user->id,'product_slug'=>'projects']);
  $this->assertDatabaseMissing('user_product_access',['user_id'=>$user->id,'product_slug'=>'communication']);
  $this->getJson('/pms/api/employees')->assertOk()->assertJsonFragment(['email'=>$user->email]);
  $payload['status']=0;
  $this->postJson('/pms/api/employees/'.$user->id,$payload)->assertOk();
  $this->withSession(['judibas_admin'=>false])->actingAs($user)->getJson('/pms/api')->assertForbidden();
 }

 public function test_my_work_visibility_comments_and_timer_rules_match_assigned_employee():void {
  \Illuminate\Support\Facades\Event::fake();
  \Illuminate\Support\Facades\Notification::fake();
  $this->withSession(['judibas_admin'=>true]);
  $workflow=DB::table('pms_workflows')->where('is_default',true)->value('id');
  $column=DB::table('pms_workflow_columns')->where('workflow_id',$workflow)->orderBy('position')->value('id');
  $project=$this->postJson('/pms/api/projects',['name'=>'Visibility Parity','start_date'=>'2026-10-09','workflow_ids'=>[$workflow]])->assertCreated()->json('project_id');
  $task=$this->postJson('/pms/api/tasks',['title'=>'Assigned parity','project_id'=>$project,'workflow_id'=>$workflow,'workflow_column_id'=>$column,'status'=>'pending'])->assertOk()->json('task.id');
  $hidden=$this->postJson('/pms/api/tasks',['title'=>'Hidden parity','project_id'=>$project,'workflow_id'=>$workflow,'workflow_column_id'=>$column,'status'=>'pending'])->assertOk()->json('task.id');
  $this->postJson('/pms/api/tasks/'.$task.'/comments',['body'=>'Super admin note'])->assertCreated();
  $this->assertDatabaseHas('pms_task_comments',['task_id'=>$task,'author_name'=>'Super Admin','user_id'=>null]);
  $user=\App\Models\User::factory()->create(['is_active'=>true]);
  DB::table('user_product_access')->insert(['user_id'=>$user->id,'product_slug'=>'projects']);
  DB::table('pms_role_users')->insert(['user_id'=>$user->id,'role_id'=>DB::table('pms_roles')->where('role_key','developer')->value('id')]);
  DB::table('pms_project_user')->insert(['user_id'=>$user->id,'project_id'=>$project]);
  DB::table('pms_task_user')->insert(['user_id'=>$user->id,'task_id'=>$task]);
  $this->withSession(['judibas_admin'=>false])->actingAs($user);
  $this->getJson('/pms/api/my-work')->assertOk()->assertJsonFragment(['title'=>'Assigned parity'])->assertJsonMissing(['title'=>'Hidden parity']);
  $this->getJson('/pms/api/tasks/'.$hidden)->assertForbidden();
  $this->postJson('/pms/api/tasks/'.$task.'/comments',['body'=>'Employee note'])->assertCreated();
  $this->postJson('/pms/api/timers/start',['task_id'=>$hidden])->assertStatus(400);
  $this->postJson('/pms/api/timers/start',['task_id'=>$task])->assertOk();
  $this->postJson('/pms/api/timers/start',['task_id'=>$task])->assertStatus(400);
  $this->postJson('/pms/api/timers/stop',['task_id'=>$task])->assertOk();
  $this->assertDatabaseHas('pms_tasks',['id'=>$task,'status'=>'in_progress']);
  $this->assertSame(0,DB::table('pms_timer_logs')->where('user_id',$user->id)->whereNull('end_time')->count());
 }
}
