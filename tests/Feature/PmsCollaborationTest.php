<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
class PmsCollaborationTest extends TestCase {
 use DatabaseTransactions;
 public function test_project_discussion_replies_seen_state_and_cross_project_access():void {
  Event::fake();Notification::fake();$this->withSession(['judibas_admin'=>true]);
  $workflow=DB::table('pms_workflows')->where('is_default',true)->value('id');
  $project=$this->postJson('/pms/api/projects',['name'=>'Collaboration parity','start_date'=>'2026-10-09','workflow_ids'=>[$workflow]])->assertCreated()->json('project_id');
  $other=$this->postJson('/pms/api/projects',['name'=>'Other collaboration parity','start_date'=>'2026-10-09','workflow_ids'=>[$workflow]])->assertCreated()->json('project_id');
  $message=$this->postJson('/pms/api/projects/'.$project.'/chat',['message'=>'Super Admin note'])->assertOk()->json('message.id');
  $this->postJson('/pms/api/projects/'.$project.'/chat',['message'=>'Thread reply','parent_id'=>$message])->assertOk();
  $this->postJson('/pms/api/projects/'.$other.'/chat',['message'=>'Wrong reply','parent_id'=>$message])->assertUnprocessable();
  $this->getJson('/pms/api/projects/'.$project.'/chat')->assertOk()->assertJsonFragment(['author_name'=>'Super Admin']);
  $this->postJson('/pms/api/projects/'.$project.'/chat/seen')->assertOk();
  $this->assertContains('super',\App\Modules\Pms\Models\ProjectChat::findOrFail($message)->seen_by);
  $user=\App\Models\User::factory()->create(['is_active'=>true]);DB::table('user_product_access')->insert(['user_id'=>$user->id,'product_slug'=>'projects']);
  $this->withSession(['judibas_admin'=>false])->actingAs($user)->getJson('/pms/api/projects/'.$project.'/chat')->assertForbidden();
  $this->deleteJson('/pms/api/project-chat/'.$message)->assertForbidden();
  DB::table('pms_project_user')->insert(['user_id'=>$user->id,'project_id'=>$project]);
  $this->getJson('/pms/api/projects/'.$project.'/chat')->assertOk();
  $own=$this->postJson('/pms/api/projects/'.$project.'/chat',['message'=>'Member note'])->assertOk()->json('message.id');
  $this->deleteJson('/pms/api/project-chat/'.$own)->assertOk();
 }
 public function test_system_health_requires_pms_permission_even_on_localhost():void {
  $this->get('/pms/system-health')->assertForbidden();
  $user=\App\Models\User::factory()->create(['is_active'=>true]);DB::table('user_product_access')->insert(['user_id'=>$user->id,'product_slug'=>'projects']);
  $this->actingAs($user)->get('/pms/system-health')->assertForbidden();
  $this->withSession(['judibas_admin'=>true])->get('/pms/system-health')->assertOk();
 }
 public function test_handoff_completion_and_filtered_exports_use_populated_data():void {
  Event::fake();Notification::fake();$this->withSession(['judibas_admin'=>true]);
  $workflow=DB::table('pms_workflows')->where('is_default',true)->value('id');
  $column=DB::table('pms_workflow_columns')->where('workflow_id',$workflow)->orderBy('position')->value('id');
  $project=$this->postJson('/pms/api/projects',['name'=>'Handoff parity','start_date'=>'2026-10-09','workflow_ids'=>[$workflow]])->assertCreated()->json('project_id');
  $task=$this->postJson('/pms/api/tasks',['title'=>'Handoff task','project_id'=>$project,'workflow_id'=>$workflow,'workflow_column_id'=>$column,'status'=>'pending'])->assertOk()->json('task.id');
  $subtask=$this->postJson('/pms/api/subtasks',['title'=>'Completion parity','task_id'=>$task])->assertOk()->json('subtask.id');
  $this->postJson('/pms/api/subtask-status',['sub_task_id'=>$subtask,'status'=>'completed'])->assertStatus(400);
  $this->postJson('/pms/api/subtask-status',['sub_task_id'=>$subtask,'status'=>'in_progress'])->assertOk();
  $this->postJson('/pms/api/subtask-status',['sub_task_id'=>$subtask,'status'=>'completed'])->assertOk();
  $this->assertDatabaseHas('pms_sub_task_pending_summaries',['sub_task_id'=>$subtask,'author_name'=>'Super Admin']);
  $this->postJson('/pms/api/task-status',['task_id'=>$task,'status'=>'in_progress'])->assertOk();
  $this->postJson('/pms/api/task-status',['task_id'=>$task,'status'=>'completed'])->assertOk();
  $this->postJson('/pms/api/task-approval',['task_id'=>$task,'approval'=>'approved'])->assertOk();
  foreach(['csv','xlsx','pdf']as $format)$this->get('/pms/api/reports/export/'.$format.'?project_id='.$project)->assertOk()->assertDownload('pms-report.'.$format);
  $destination=DB::table('pms_departments')->insertGetId(['dept_name'=>'Handoff destination','status'=>1,'created_at'=>now(),'updated_at'=>now()]);
  $this->getJson('/pms/api/tasks/'.$task.'/handoff')->assertOk();
  $new=$this->postJson('/pms/api/tasks/'.$task.'/handoff',['destination_department_id'=>$destination,'notes'=>'Parity handoff'])->assertCreated()->json('destination_task_id');
  $this->assertDatabaseHas('pms_tasks',['id'=>$new,'department_id'=>$destination,'project_id'=>null]);
  $next=DB::table('pms_workflow_columns')->where('workflow_id',$workflow)->orderBy('position')->skip(1)->value('id');
  $this->patchJson('/pms/api/tasks/'.$new.'/board',['workflow_id'=>$workflow,'workflow_column_id'=>$next])->assertOk();
  $this->assertDatabaseHas('pms_tasks',['id'=>$new,'department_id'=>$destination,'project_id'=>null,'workflow_column_id'=>$next]);
  $this->postJson('/pms/api/tasks/'.$task.'/handoff',['destination_department_id'=>$destination])->assertUnprocessable();
  DB::table('pms_projects')->where('id',$project)->update(['approval'=>'approved']);DB::table('pms_department_project')->insert(['department_id'=>$destination,'project_id'=>$project,'created_at'=>now(),'updated_at'=>now()]);
  $this->getJson('/pms/api/tasks/'.$new.'/placement-options')->assertOk();
  $this->postJson('/pms/api/tasks/'.$new.'/place',['project_id'=>$project])->assertOk();
  $this->assertDatabaseHas('pms_tasks',['id'=>$new,'project_id'=>$project]);
 }
 public function test_scheduled_cleanup_preserves_original_approval_eligibility():void {
  $eligible=DB::table('pms_tasks')->insertGetId(['title'=>'Eligible cleanup','approval'=>'approved','created_at'=>now(),'updated_at'=>now()]);
  $blocked=DB::table('pms_tasks')->insertGetId(['title'=>'Blocked cleanup','approval'=>'approved','created_at'=>now(),'updated_at'=>now()]);
  $pending=DB::table('pms_tasks')->insertGetId(['title'=>'Pending cleanup','approval'=>'pending','created_at'=>now(),'updated_at'=>now()]);
  $child=DB::table('pms_subtasks')->insertGetId(['task_id'=>$blocked,'title'=>'Pending child','approval'=>'pending','created_at'=>now(),'updated_at'=>now()]);
  $this->artisan('pms:delete-approved-tasks')->assertExitCode(0);
  $this->assertDatabaseMissing('pms_tasks',['id'=>$eligible]);$this->assertDatabaseHas('pms_tasks',['id'=>$blocked]);$this->assertDatabaseHas('pms_tasks',['id'=>$pending]);$this->assertDatabaseHas('pms_subtasks',['id'=>$child]);
 }
}
