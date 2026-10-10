<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
class PmsAdministrationTest extends TestCase {
 use DatabaseTransactions;
 public function test_permission_catalog_keeps_keys_fixed_when_renamed():void {
  $this->withSession(['judibas_admin'=>true]);$this->postJson('/pms/api/permissions',['permission_name'=>'Parity permission','permission_key'=>'parity-custom'])->assertOk();
  $id=DB::table('pms_permissions')->where('permission_key','parity-custom')->value('id');$this->postJson('/pms/api/permissions/'.$id,['permission_name'=>'Updated permission','permission_key'=>'different-key'])->assertOk();$this->assertDatabaseHas('pms_permissions',['id'=>$id,'permission_key'=>'parity-custom']);$this->deleteJson('/pms/api/permissions/'.$id)->assertOk();$this->assertDatabaseMissing('pms_permissions',['id'=>$id]);
 }
 public function test_subtask_assignment_removal_and_task_deletion():void {
  Event::fake();\Illuminate\Support\Facades\Notification::fake();$u=$this->employee();$this->withSession(['judibas_admin'=>true]);$workflow=DB::table('pms_workflows')->where('is_default',true)->value('id');$column=DB::table('pms_workflow_columns')->where('workflow_id',$workflow)->orderBy('position')->value('id');
  $project=$this->postJson('/pms/api/projects',['name'=>'Assignment parity','start_date'=>'2026-10-09','workflow_ids'=>[$workflow]])->assertCreated()->json('project_id');
  $task=$this->postJson('/pms/api/tasks',['title'=>'Assigned task','project_id'=>$project,'workflow_id'=>$workflow,'workflow_column_id'=>$column,'status'=>'pending'])->assertOk()->json('task.id');
  $subtask=$this->postJson('/pms/api/subtasks',['task_id'=>$task,'title'=>'Assigned subtask'])->assertOk()->json('subtask.id');
  $this->postJson('/pms/api/task-assignees/assign',['task_id'=>$task,'sub_task_id'=>$subtask,'user_id'=>$u->id])->assertCreated();$this->assertDatabaseHas('pms_subtask_user',['subtask_id'=>$subtask,'user_id'=>$u->id]);
  $this->postJson('/pms/api/task-assignees/remove',['task_id'=>$task,'subtask_id'=>$subtask,'user_id'=>$u->id])->assertOk();$this->assertDatabaseMissing('pms_subtask_user',['subtask_id'=>$subtask,'user_id'=>$u->id]);
  $this->deleteJson('/pms/api/tasks/'.$task)->assertOk();$this->assertDatabaseMissing('pms_tasks',['id'=>$task]);$this->assertDatabaseMissing('pms_subtasks',['id'=>$subtask]);
 }
 public function test_reminder_daily_deduplication_uses_nested_notification_payload():void {
  Event::fake();$u=$this->employee();$task=DB::table('pms_tasks')->insertGetId(['title'=>'Reminder parity','status'=>'in_progress','due_date'=>now()->addDay(),'created_at'=>now()->subDays(10),'updated_at'=>now()]);DB::table('pms_task_user')->insert(['task_id'=>$task,'user_id'=>$u->id,'created_at'=>now(),'updated_at'=>now()]);
  $this->artisan('pms:send-reminders')->assertExitCode(0);$this->artisan('pms:send-reminders')->assertExitCode(0);$this->assertEquals(1,DB::table('pms_notifications')->where('notifiable_id',$u->id)->where('type',\App\Modules\Pms\Notifications\TaskReminderNotification::class)->count());
 }
 private function employee(){ $u=\App\Models\User::factory()->create(['is_active'=>true,'status'=>1]);DB::table('user_product_access')->insert(['user_id'=>$u->id,'product_slug'=>'projects']);return $u; }
 public function test_profile_requires_current_password_and_removal_preserves_other_products():void {
  Event::fake();$u=$this->employee();DB::table('user_product_access')->insert(['user_id'=>$u->id,'product_slug'=>'communication']);$this->actingAs($u);
  $this->postJson('/pms/api/profile',['name'=>'Updated employee'])->assertOk();$this->assertDatabaseHas('users',['id'=>$u->id,'name'=>'Updated employee']);
  $this->postJson('/pms/api/profile',['name'=>'Updated employee','current-password'=>'incorrect','new-password'=>'newpassword123','repeat-password'=>'newpassword123'])->assertUnprocessable();
  $this->withSession(['judibas_admin'=>true])->deleteJson('/pms/api/employees/'.$u->id)->assertOk();
  $this->assertDatabaseHas('users',['id'=>$u->id]);$this->assertDatabaseHas('user_product_access',['user_id'=>$u->id,'product_slug'=>'communication']);$this->assertDatabaseMissing('user_product_access',['user_id'=>$u->id,'product_slug'=>'projects']);
  $this->withSession(['judibas_admin'=>false])->getJson('/pms/api/')->assertForbidden();
 }
 public function test_collaborator_invitation_is_private_one_use_and_creates_assignment():void {
  Event::fake();$u=$this->employee();$this->withSession(['judibas_admin'=>true]);$workflow=DB::table('pms_workflows')->where('is_default',true)->value('id');
  $project=$this->postJson('/pms/api/projects',['name'=>'Invitation parity','start_date'=>'2026-10-09','workflow_ids'=>[$workflow]])->assertCreated()->json('project_id');
  $this->postJson('/pms/api/invitations',['recipient_id'=>$u->id,'invitable_type'=>'project','invitable_id'=>$project,'role'=>'member'])->assertCreated();
  $inv=DB::table('pms_invitations')->where('recipient_id',$u->id)->where('invitable_id',$project)->first();$this->assertDatabaseHas('pms_notifications',['notifiable_id'=>$u->id]);
  $this->postJson('/pms/api/invitations/'.$inv->token.'/respond',['action'=>'accept'])->assertForbidden();
  $this->withSession(['judibas_admin'=>false])->actingAs($u)->postJson('/pms/api/invitations/'.$inv->token.'/respond',['action'=>'accept'])->assertOk();
  $this->assertDatabaseHas('pms_project_user',['project_id'=>$project,'user_id'=>$u->id]);$this->postJson('/pms/api/invitations/'.$inv->token.'/respond',['action'=>'accept'])->assertStatus(410);
 }
}
